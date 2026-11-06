<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttentionRequest;
use App\Models\AuditLog;
use App\Models\ClosedConversation;
use App\Models\Customer;
use App\Models\OperationalSetting;
use App\Models\TeamContact;
use App\Services\Integrations\GhlClient;
use App\Services\Integrations\GhlConversations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Conversaciones de WhatsApp, Instagram, Facebook y correo dentro del panel.
 * Por ahora solo para Soporte, mientras se construye y se prueba.
 */
class ConversationController extends Controller
{
    /** Con esta etiqueta Cata no contesta (filtro de los canales del agente en GHL). */
    private const NO_BOT_TAG = 'hh_no_bot';

    /** Con esta etiqueta Cata tampoco contesta: el cliente tiene una reserva vigente, desde que la crea hasta que se cierra. */
    private const STAY_TAG = 'hh_estadia_activa';

    public function index(GhlConversations $ghl): View
    {
        return view('admin.conversations.index', ['configured' => $ghl->isConfigured(), 'notice' => request()->integer('aviso') ?: null]);
    }

    public function list(Request $request, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate([
            'vista' => ['nullable', Rule::in(['por_atender', 'esperando', 'todas', 'equipo'])],
            'canal' => ['nullable', Rule::in(array_keys(GhlConversations::CHANNELS))],
            'q' => ['nullable', 'string', 'max:80'],
            'antes' => ['nullable', 'integer', 'min:1'],
            'aviso' => ['nullable', 'integer', 'min:1'],
        ]);

        // Un aviso de atención abre la conversación de ese cliente, sin poner su teléfono en la dirección.
        if (isset($data['aviso'])) {
            $phone = AttentionRequest::find($data['aviso'])?->phone;

            return $phone ? $this->guarded(fn () => $ghl->search('todas', null, $phone)) : response()->json(['items' => [], 'next' => null]);
        }

        $team = TeamContact::pluck('ghl_contact_id')->all();

        if (($data['vista'] ?? null) === 'equipo') {
            return $this->guarded(fn () => $this->withTeam($team === [] ? ['items' => [], 'next' => null] : $ghl->searchContacts(array_slice($team, 0, 30)), $team));
        }

        return $this->guarded(function () use ($ghl, $data, $team) {
            $result = $this->withTeam($ghl->search(
                $data['vista'] ?? 'todas',
                $data['canal'] ?? null,
                $data['q'] ?? null,
                isset($data['antes']) ? (int) $data['antes'] : null,
            ), $team);

            // Al buscar se ve todo; en la bandeja, el equipo va aparte de los clientes.
            if (empty($data['q'])) {
                $result['items'] = array_values(array_filter($result['items'], fn (array $item) => ! $item['team']));
            }

            if (($data['vista'] ?? null) === 'esperando') {
                $result['items'] = array_values(array_filter($result['items'], fn (array $item) => ! $item['closed']));
            }

            if (($data['vista'] ?? null) === 'por_atender') {
                $result['items'] = array_values(array_filter($result['items'], fn (array $item) => $item['pending']));
                // Vienen de la más nueva a la más vieja: pasado el punto de partida no hay nada más.
                $result['next'] = ($result['next'] ?? 0) >= $result['since'] ? $result['next'] : null;
            }

            return $result;
        });
    }

    /**
     * Marca equipo y seguimiento. Por atender = lo último lo escribió el
     * cliente después del punto de partida: los no leídos de GHL arrastran
     * historia y no sirven para esto.
     *
     * @param  array<int, string>  $team
     */
    private function withTeam(array $result, array $team): array
    {
        $since = OperationalSetting::current()->conversations_since?->getTimestampMs() ?? 0;

        $closures = ClosedConversation::whereIn('ghl_conversation_id', array_column($result['items'], 'id'))->pluck('closed_at', 'ghl_conversation_id');

        $result['items'] = array_map(function (array $item) use ($team, $since, $closures) {
            // Un mensaje posterior al alta la devuelve al seguimiento sola.
            $closed = isset($closures[$item['id']]) && $closures[$item['id']]->getTimestampMs() >= $item['at'];

            return $item + [
                'team' => in_array($item['contact_id'], $team, true),
                'closed' => $closed,
                'pending' => ! $closed && $item['direction'] === 'inbound' && $item['at'] >= $since,
            ];
        }, $result['items']);

        return $result + ['since' => $since];
    }

    public function show(Request $request, string $conversation, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate(['contacto' => ['nullable', 'string', 'alpha_num', 'max:64']]);

        return $this->guarded(function () use ($conversation, $ghl, $data) {
            $thread = $ghl->open($conversation, $data['contacto'] ?? null);

            return $thread + [
                'cata' => $this->cataState($thread['contact']),
                'customer' => $this->customerCard($thread['contact']),
                'team' => $thread['contact_id'] !== '' && TeamContact::where('ghl_contact_id', $thread['contact_id'])->exists(),
                'closed' => (ClosedConversation::where('ghl_conversation_id', $conversation)->first()?->closed_at?->getTimestampMs() ?? -1) >= $thread['last_at'],
            ];
        });
    }

    public function reply(Request $request, string $conversation, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message = trim($data['message']);

        return $this->guarded(function () use ($request, $conversation, $ghl, $message) {
            $channel = $ghl->reply($conversation, $message);
            AuditLog::record($request->user()->id, 'conversacion.respondida', 'GhlConversation', $conversation, null, [
                'canal' => $channel,
                'mensaje' => $message,
            ]);

            return ['ok' => true];
        });
    }

    public function cata(Request $request, string $conversation, GhlConversations $ghl, GhlClient $client): JsonResponse
    {
        $data = $request->validate(['on' => ['required', 'boolean']]);

        return $this->guarded(function () use ($request, $conversation, $ghl, $client, $data) {
            $contactId = $this->contactOf($conversation, $ghl);

            $data['on'] ? $client->removeTags($contactId, [self::NO_BOT_TAG]) : $client->addTags($contactId, [self::NO_BOT_TAG]);
            AuditLog::record($request->user()->id, $data['on'] ? 'conversacion.cata_encendida' : 'conversacion.cata_apagada', 'GhlConversation', $conversation, null, null);

            return ['ok' => true];
        });
    }

    public function updateContact(Request $request, string $conversation, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
        ], ['first_name.required' => 'El nombre no puede quedar vacío.', 'email.email' => 'Ese correo no es válido.']);

        return $this->guarded(function () use ($request, $conversation, $ghl, $data) {
            $contactId = $this->contactOf($conversation, $ghl);
            $old = $ghl->contact($contactId);
            $ghl->updateContact($contactId, trim($data['first_name']), trim((string) ($data['last_name'] ?? '')) ?: null, $data['email'] ?? null);
            AuditLog::record($request->user()->id, 'conversacion.ficha_editada', 'GhlConversation', $conversation,
                ['nombre' => $old['first_name'], 'apellido' => $old['last_name'], 'correo' => $old['email']],
                ['nombre' => $data['first_name'], 'apellido' => $data['last_name'] ?? null, 'correo' => $data['email'] ?? null]);

            return ['ok' => true];
        });
    }

    public function team(Request $request, string $conversation, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate(['on' => ['required', 'boolean']]);

        return $this->guarded(function () use ($request, $conversation, $ghl, $data) {
            $contactId = $this->contactOf($conversation, $ghl);

            if ($data['on']) {
                TeamContact::firstOrCreate(['ghl_contact_id' => $contactId], ['created_by' => $request->user()->id]);
            } else {
                TeamContact::where('ghl_contact_id', $contactId)->delete();
            }
            AuditLog::record($request->user()->id, $data['on'] ? 'conversacion.marcada_equipo' : 'conversacion.quitada_equipo', 'GhlConversation', $conversation, null, null);

            return ['ok' => true];
        });
    }

    public function close(Request $request, string $conversation, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate(['on' => ['required', 'boolean']]);

        return $this->guarded(function () use ($request, $conversation, $ghl, $data) {
            $this->contactOf($conversation, $ghl);

            if ($data['on']) {
                ClosedConversation::updateOrCreate(['ghl_conversation_id' => $conversation], ['closed_at' => now(), 'closed_by' => $request->user()->id]);
            } else {
                ClosedConversation::where('ghl_conversation_id', $conversation)->delete();
            }
            AuditLog::record($request->user()->id, $data['on'] ? 'conversacion.dada_de_alta' : 'conversacion.reabierta', 'GhlConversation', $conversation, null, null);

            return ['ok' => true];
        });
    }

    private function contactOf(string $conversation, GhlConversations $ghl): string
    {
        $contactId = $ghl->thread($conversation)['contact_id'];

        if ($contactId === '') {
            throw new RuntimeException('No se pudo identificar el contacto.');
        }

        return $contactId;
    }

    /** @return array{active: bool, reason: ?string} */
    private function cataState(?array $contact): array
    {
        $tags = $contact['tags'] ?? [];

        return match (true) {
            in_array(self::NO_BOT_TAG, $tags, true) => ['active' => false, 'reason' => 'Apagada para este cliente'],
            in_array(self::STAY_TAG, $tags, true) => ['active' => false, 'reason' => 'En pausa: tiene una reserva vigente'],
            default => ['active' => true, 'reason' => null],
        };
    }

    /** Solo datos propios del cliente: nunca con quién vino (ver Booking::guestsVisibleTo). */
    private function customerCard(?array $contact): ?array
    {
        $phone = $contact['phone'] ?? '';
        $customer = $phone !== '' ? Customer::where('phone_e164', $phone)->first() : null;

        if (! $customer) {
            return null;
        }

        $last = $customer->bookings()->latest('starts_at')->first();

        return [
            'url' => route('admin.customers.show', $customer),
            'bookings' => $customer->bookings()->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])->count(),
            'last_booking' => $last ? [
                'code' => $last->code,
                'url' => route('reservations.show', $last->code),
                'date' => $last->starts_at->timezone('America/Santiago')->format('d/m/Y H:i'),
                'status' => $last->booking_status,
            ] : null,
        ];
    }

    private function guarded(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (RequestException $e) {
            $status = $e->response->status();
            Log::warning('Conversaciones GHL: error '.$status, ['path' => $e->response->effectiveUri()?->getPath(), 'mensaje' => $e->response->json('message')]);

            return response()->json(['error' => in_array($status, [401, 403], true)
                ? 'GHL rechazó el permiso. Revisa que la integración tenga acceso a conversaciones y mensajes.'
                : 'GHL respondió con un error ('.$status.'). Intenta de nuevo.'], 502);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (ConnectionException) {
            return response()->json(['error' => 'GHL no responde en este momento. Intenta de nuevo.'], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo completar. Intenta de nuevo.'], 500);
        }
    }
}
