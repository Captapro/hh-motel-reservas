<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
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

    /** Con esta etiqueta Cata tampoco contesta: el cliente está dentro del motel. */
    private const STAY_TAG = 'hh_estadia_activa';

    public function index(GhlConversations $ghl): View
    {
        return view('admin.conversations.index', ['configured' => $ghl->isConfigured()]);
    }

    public function list(Request $request, GhlConversations $ghl): JsonResponse
    {
        $data = $request->validate([
            'vista' => ['nullable', Rule::in(['por_atender', 'esperando', 'todas'])],
            'canal' => ['nullable', Rule::in(array_keys(GhlConversations::CHANNELS))],
            'q' => ['nullable', 'string', 'max:80'],
            'antes' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->guarded(fn () => $ghl->search(
            $data['vista'] ?? 'todas',
            $data['canal'] ?? null,
            $data['q'] ?? null,
            isset($data['antes']) ? (int) $data['antes'] : null,
        ));
    }

    public function show(string $conversation, GhlConversations $ghl): JsonResponse
    {
        return $this->guarded(function () use ($conversation, $ghl) {
            $thread = $ghl->thread($conversation);
            $contact = $thread['contact_id'] !== '' ? $ghl->contact($thread['contact_id']) : null;

            return $thread + ['contact' => $contact, 'cata' => $this->cataState($contact), 'customer' => $this->customerCard($contact)];
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
            $contactId = $ghl->thread($conversation)['contact_id'];

            if ($contactId === '') {
                throw new RuntimeException('No se pudo identificar el contacto.');
            }

            $data['on'] ? $client->removeTags($contactId, [self::NO_BOT_TAG]) : $client->addTags($contactId, [self::NO_BOT_TAG]);
            AuditLog::record($request->user()->id, $data['on'] ? 'conversacion.cata_encendida' : 'conversacion.cata_apagada', 'GhlConversation', $conversation, null, null);

            return ['ok' => true];
        });
    }

    /** @return array{active: bool, reason: ?string} */
    private function cataState(?array $contact): array
    {
        $tags = $contact['tags'] ?? [];

        return match (true) {
            in_array(self::NO_BOT_TAG, $tags, true) => ['active' => false, 'reason' => 'Apagada para este cliente'],
            in_array(self::STAY_TAG, $tags, true) => ['active' => false, 'reason' => 'En pausa: el cliente tiene una estadía activa'],
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
