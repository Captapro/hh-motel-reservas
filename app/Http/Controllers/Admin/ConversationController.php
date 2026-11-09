<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttentionRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ClosedConversation;
use App\Models\Customer;
use App\Models\TeamContact;
use App\Services\Integrations\GhlClient;
use App\Services\Integrations\GhlConversations;
use App\Support\VoiceNote;
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
 * Quién entra se define en Perfiles y accesos; los chats del equipo quedan
 * solo para administración.
 */
class ConversationController extends Controller
{
    /** Con esta etiqueta Cata no contesta (filtro de los canales del agente en GHL). */
    private const NO_BOT_TAG = 'hh_no_bot';

    /** Con esta etiqueta Cata tampoco contesta: el cliente tiene una reserva vigente, desde que la crea hasta que se cierra. */
    private const STAY_TAG = 'hh_estadia_activa';

    public function index(GhlConversations $ghl): View
    {
        return view('admin.conversations.index', [
            'configured' => $ghl->isConfigured(),
            'notice' => request()->integer('aviso') ?: null,
            'pendingBooking' => preg_match('/^[A-Za-z0-9-]{1,40}$/', (string) request()->query('reserva')) ? request()->query('reserva') : null,
            'pendingOpen' => preg_match('/^[A-Za-z0-9]{1,64}$/', (string) request()->query('abrir')) ? request()->query('abrir') : null,
            'pendingTeam' => request()->boolean('equipo') && $this->seesTeam(request()),
            'seesTeam' => $this->seesTeam(request()),
        ]);
    }

    public function list(Request $request, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate([
            'vista' => ['nullable', Rule::in(['por_atender', 'esperando', 'todas', 'equipo'])],
            'canal' => ['nullable', Rule::in(array_keys(GhlConversations::CHANNELS))],
            'q' => ['nullable', 'string', 'max:80'],
            'antes' => ['nullable', 'integer', 'min:1'],
            'aviso' => ['nullable', 'integer', 'min:1'],
            'reserva' => ['nullable', 'string', 'alpha_dash', 'max:40'],
        ]);

        // Un aviso de atención abre la conversación de ese cliente, sin poner su teléfono en la dirección.
        if (isset($data['aviso'])) {
            $phone = AttentionRequest::find($data['aviso'])?->phone;

            return $phone
                ? $this->guarded(fn () => $this->visibleTo($request, $ghl->withFollowUp($ghl->search('todas', null, $phone), TeamContact::pluck('ghl_contact_id')->all())))
                : response()->json(['items' => [], 'next' => null]);
        }

        $team = TeamContact::pluck('ghl_contact_id')->all();

        // Desde una reserva: el teléfono se resuelve aquí para que no viaje en la dirección.
        if (isset($data['reserva'])) {
            $customer = Booking::with('customer')->where('code', $data['reserva'])->first()?->customer;

            return $customer
                ? $this->guarded(fn () => $this->visibleTo($request, $ghl->withFollowUp($this->conversationsOf($ghl, $customer), $team)))
                : response()->json(['items' => [], 'next' => null]);
        }

        if (($data['vista'] ?? null) === 'equipo') {
            abort_unless($this->seesTeam($request), 403);

            return $this->guarded(fn () => $ghl->withFollowUp($team === [] ? ['items' => [], 'next' => null] : $ghl->searchContacts(array_slice($team, 0, 30)), $team));
        }

        return $this->guarded(function () use ($request, $ghl, $data, $team) {
            $result = $ghl->withFollowUp($ghl->search(
                $data['vista'] ?? 'todas',
                $data['canal'] ?? null,
                $data['q'] ?? null,
                isset($data['antes']) ? (int) $data['antes'] : null,
            ), $team);

            // Al buscar, administración ve todo; en la bandeja, el equipo va aparte de los clientes.
            $result = empty($data['q'])
                ? ['items' => array_values(array_filter($result['items'], fn (array $item) => ! $item['team']))] + $result
                : $this->visibleTo($request, $result);

            // Por atender y Esperando cliente son seguimiento: parten del punto de partida.
            $view = $data['vista'] ?? null;
            if (in_array($view, ['por_atender', 'esperando'], true)) {
                $result['items'] = array_values(array_filter($result['items'], fn (array $item) => $view === 'por_atender'
                    ? $item['pending']
                    : ! $item['closed'] && $item['at'] >= $result['waiting_since']));
                // Quien está en la habitación va primero en lo que hay que atender.
                if ($view === 'por_atender') {
                    $result['items'] = collect($result['items'])->sortBy(fn (array $item) => $item['in_room'] === null)->values()->all();
                }
                // Vienen de la más nueva a la más vieja: pasado el punto de partida no hay nada más.
                $floor = $view === 'esperando' ? $result['waiting_since'] : $result['since'];
                $result['next'] = ($result['next'] ?? 0) >= $floor ? $result['next'] : null;
            }

            return $result;
        });
    }

    /** @return array{items: array<int, array<string, mixed>>, next: ?int} */
    private function conversationsOf(GhlConversations $ghl, Customer $customer): array
    {
        $found = $customer->ghl_contact_id ? $ghl->searchContacts([$customer->ghl_contact_id]) : ['items' => [], 'next' => null];

        return $found['items'] === [] && $customer->phone_e164 ? $ghl->search('todas', null, $customer->phone_e164) : $found;
    }

    /** Los chats del equipo pueden ser internos: los anfitriones no los ven. */
    private function seesTeam(Request $request): bool
    {
        return $request->user()->isAdministrador();
    }

    private function visibleTo(Request $request, array $result): array
    {
        if (! $this->seesTeam($request)) {
            $result['items'] = array_values(array_filter($result['items'], fn (array $item) => ! $item['team']));
        }

        return $result;
    }

    public function show(Request $request, string $conversation, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate(['contacto' => ['nullable', 'string', 'alpha_num', 'max:64']]);

        return $this->guarded(function () use ($request, $conversation, $ghl, $data) {
            $thread = $ghl->open($conversation, $data['contacto'] ?? null);
            $this->guardTeam($request, $thread['contact_id']);

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
        $data = $request->validate([
            'message' => ['nullable', 'required_without:file', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:'.GhlConversations::MAX_FILE_KB, 'extensions:'.implode(',', [...array_keys(GhlConversations::MEDIA_TYPES), ...VoiceNote::CONVERT_EXTENSIONS])],
        ], [
            'message.required_without' => 'Escribe un mensaje o adjunta un archivo.',
            'file.max' => 'El archivo pesa más de 5 MB.',
            'file.extensions' => 'Ese tipo de archivo no se puede enviar.',
        ]);
        $message = trim((string) ($data['message'] ?? ''));
        $file = $request->file('file');

        return $this->guarded(function () use ($request, $conversation, $ghl, $message, $file) {
            $this->contactOf($request, $conversation, $ghl);
            $converted = $file && VoiceNote::needsConversion($file) ? VoiceNote::toOgg($file) : null;
            $file = $converted ?? $file;
            try {
                $channel = $ghl->reply($conversation, $message, $file);
            } finally {
                if ($converted) {
                    @unlink($converted->getRealPath());
                }
            }
            $ghl->forgetAlerts();
            AuditLog::record($request->user()->id, 'conversacion.respondida', 'GhlConversation', $conversation, null, array_filter([
                'canal' => $channel,
                'mensaje' => $message,
                'adjunto' => $file?->getClientOriginalName(),
            ]));

            return ['ok' => true];
        });
    }

    /**
     * Desde la ficha de una reserva: manda el mensaje (datos de pago o pedido de reseña) directo a la
     * conversación del cliente, sin copiar y pegar en WhatsApp. Usa su chat de WhatsApp más reciente;
     * si no tiene o la ventana de 24 h está cerrada, lo dice para que se use el botón de WhatsApp.
     */
    public function sendForBooking(Request $request, string $code, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(['pago', 'resena'])],
        ], ['message.required' => 'El mensaje está vacío.']);
        $booking = Booking::with('customer')->where('code', $code)->firstOrFail();

        return $this->guarded(function () use ($request, $ghl, $booking, $data) {
            $chat = collect($this->conversationsOf($ghl, $booking->customer)['items'])
                ->where('channel', 'whatsapp')->sortByDesc('at')->first();
            if (! $chat) {
                throw new RuntimeException('Este cliente no tiene conversación de WhatsApp en el sistema. Usa "Contactar por WhatsApp".');
            }
            $this->contactOf($request, $chat['id'], $ghl);
            $ghl->reply($chat['id'], trim($data['message']));
            $ghl->forgetAlerts();
            if ($data['kind'] === 'pago') {
                $booking->update(['payment_instructions_sent_at' => now()]);
            }
            AuditLog::record($request->user()->id, 'conversacion.respondida', 'GhlConversation', $chat['id'], null, [
                'canal' => 'whatsapp', 'mensaje' => trim($data['message']), 'reserva' => $booking->code, 'tipo' => $data['kind'],
            ]);

            return ['ok' => true, 'conversation' => route('admin.conversations.index', ['abrir' => $chat['id']])];
        });
    }

    public function cata(Request $request, string $conversation, GhlConversations $ghl, GhlClient $client): JsonResponse
    {
        $data = $request->validate(['on' => ['required', 'boolean']]);

        return $this->guarded(function () use ($request, $conversation, $ghl, $client, $data) {
            $contactId = $this->contactOf($request, $conversation, $ghl);

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
            $contactId = $this->contactOf($request, $conversation, $ghl);
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
        abort_unless($this->seesTeam($request), 403);
        $data = $request->validate(['on' => ['required', 'boolean']]);

        return $this->guarded(function () use ($request, $conversation, $ghl, $data) {
            $contactId = $this->contactOf($request, $conversation, $ghl);

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
            $this->contactOf($request, $conversation, $ghl);

            if ($data['on']) {
                ClosedConversation::updateOrCreate(['ghl_conversation_id' => $conversation], ['closed_at' => now(), 'closed_by' => $request->user()->id]);
            } else {
                ClosedConversation::where('ghl_conversation_id', $conversation)->delete();
            }
            $ghl->forgetAlerts();
            AuditLog::record($request->user()->id, $data['on'] ? 'conversacion.dada_de_alta' : 'conversacion.reabierta', 'GhlConversation', $conversation, null, null);

            return ['ok' => true];
        });
    }

    private function contactOf(Request $request, string $conversation, GhlConversations $ghl): string
    {
        $contactId = $ghl->thread($conversation)['contact_id'];

        if ($contactId === '') {
            throw new RuntimeException('No se pudo identificar el contacto.');
        }

        $this->guardTeam($request, $contactId);

        return $contactId;
    }

    private function guardTeam(Request $request, string $contactId): void
    {
        if (! $this->seesTeam($request) && TeamContact::where('ghl_contact_id', $contactId)->exists()) {
            throw new RuntimeException('Esta conversación es interna del equipo.');
        }
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

        $bookings = $customer->bookings()->orderByDesc('starts_at')->orderByDesc('id')->get();
        $closed = ['CANCELADA', 'EXPIRADA'];
        $current = $bookings->first(fn (Booking $b) => $this->isInRoom($b))
            ?? $bookings->filter(fn (Booking $b) => in_array($b->booking_status, ['PENDIENTE_PAGO', 'RETENIDA', 'CONFIRMADA'], true) && $b->ends_at->gte(now()))->last()
            ?? $bookings->first(fn (Booking $b) => ! in_array($b->booking_status, $closed, true))
            ?? $bookings->first();

        return [
            'url' => auth()->user()->canAccess('clientes') ? route('admin.customers.show', $customer) : null,
            'bookings' => $bookings->whereNotIn('booking_status', $closed)->count(),
            'current' => $current ? $this->bookingRow($current) : null,
            'recent' => $bookings->take(5)->map(fn (Booking $b) => $this->bookingRow($b))->values()->all(),
        ];
    }

    private function isInRoom(Booking $booking): bool
    {
        return $booking->booking_status === 'CHECK_IN' && ! $booking->checked_out_at;
    }

    private function bookingRow(Booking $booking): array
    {
        return [
            'code' => $booking->code,
            'url' => route('reservations.show', $booking->code),
            'date' => $booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i'),
            'state' => $this->stayState($booking),
        ];
    }

    /** Dónde está la persona respecto de esa reserva, dicho como lo diría recepción. */
    private function stayState(Booking $booking): string
    {
        if ($booking->checked_out_at) {
            return 'Ya salió';
        }

        return match ($booking->agendaState()['key']) {
            'en_alojamiento' => 'En la habitación',
            'finalizada' => 'Ya salió',
            'por_llegar' => 'Próximo a llegar',
            'atrasada' => 'Atrasado, aún sin ingreso',
            default => $booking->agendaState()['label'],
        };
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
