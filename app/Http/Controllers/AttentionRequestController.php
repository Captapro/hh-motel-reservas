<?php

namespace App\Http\Controllers;

use App\Models\AttentionRequest;
use App\Services\Integrations\GhlConversations;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traspaso a una persona: el flujo de GHL avisa cuando Cata deriva un chat, y
 * recepción ve el contacto en todas sus pantallas hasta marcarlo atendido.
 */
class AttentionRequestController extends Controller
{
    private const WAITING_ROWS = 3;

    private const CHANNEL_LABELS = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook'];

    /** Webhook de GHL, autenticado con GHL_INBOUND_SECRET en la URL (?token=...). */
    public function store(Request $request): JsonResponse
    {
        $secret = (string) config('services.ghl.inbound_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->query('token'))) {
            return response()->json(['result' => 'no_autorizado'], 401);
        }

        $raw = (string) ($request->input('phone') ?? $request->input('contact.phone') ?? '');
        if (! Phone::looksComplete($raw)) {
            return response()->json(['result' => 'sin_telefono']);
        }

        $name = trim((string) ($request->input('full_name') ?? $request->input('contact.name') ?? ''));
        $reason = trim((string) ($request->input('motivo') ?? ''));

        // Si ya espera atención, no se duplica: se actualiza el mismo aviso.
        $attention = AttentionRequest::open()->firstOrNew(['phone' => Phone::toE164($raw)]);
        $attention->name = $name !== '' ? Str::limit($name, 120, '') : $attention->name;
        $attention->reason = $reason !== '' ? Str::limit($reason, 480, '') : $attention->reason;
        $attention->save();

        return response()->json(['result' => 'avisado']);
    }

    public function pending(Request $request, GhlConversations $ghl): JsonResponse
    {
        // Quien tiene Conversaciones atiende ahí; el resto sigue yendo a WhatsApp.
        $inbox = $request->user()->canAccess('conversaciones');

        $handoffs = AttentionRequest::open()->orderBy('created_at')->limit(10)->get();

        $rows = $handoffs->map(fn (AttentionRequest $a) => [
            'id' => $a->id,
            'name' => $a->name ?: $a->phone,
            'phone' => $a->phone,
            'reason' => $a->reason,
            'whatsapp' => $a->whatsappUrl(),
            'conversation' => $inbox ? route('admin.conversations.index', ['aviso' => $a->id]) : null,
            'since' => $a->created_at->copy()->timezone('America/Santiago')->format('H:i'),
            'attend_url' => route('attention.attend', $a),
        ])->values();

        return response()->json($inbox ? $rows->concat($this->waitingRows($ghl, $handoffs->pluck('phone')->all(), $request->user()->isAdministrador())) : $rows);
    }

    /**
     * Mensajes sin responder. Se muestran hasta tres para no tapar el tablero;
     * quien ya tiene aviso de traspaso no se repite. Los chats del equipo son
     * solo para administración, igual que en la bandeja.
     *
     * @param  array<int, string>  $handoffPhones
     */
    private function waitingRows(GhlConversations $ghl, array $handoffPhones, bool $seesTeam): array
    {
        $waiting = collect($ghl->alerts())
            ->reject(fn (array $item) => in_array($item['phone'], $handoffPhones, true) || ($item['team'] && ! $seesTeam))
            ->values();

        $rows = $waiting->take(self::WAITING_ROWS)->map(fn (array $item) => [
            'id' => 'conversacion-'.$item['id'].'-'.$item['at'],
            'kind' => 'inbox',
            'urgent' => $item['in_room'] !== null,
            'title' => $item['in_room'] !== null ? $item['name'].' escribe desde la habitación '.$item['in_room'] : $item['name'].' espera respuesta',
            'detail' => ($item['team'] ? 'Equipo · ' : '').self::CHANNEL_LABELS[$item['channel']].' · '.Carbon::createFromTimestampMs($item['at'])->timezone('America/Santiago')->format('H:i'),
            'conversation' => $this->waitingLink($item),
        ])->all();

        $more = $waiting->count() - self::WAITING_ROWS;
        if ($more > 0) {
            $rows[] = [
                'id' => 'conversaciones-mas',
                'kind' => 'inbox',
                'title' => $more === 1 ? '1 conversación más por atender' : $more.' conversaciones más por atender',
                'detail' => '',
                'conversation' => $this->waitingLink($waiting[self::WAITING_ROWS]),
            ];
        }

        return $rows;
    }

    private function waitingLink(array $item): string
    {
        return route('admin.conversations.index', ['abrir' => $item['id']] + ($item['team'] ? ['equipo' => 1] : []));
    }

    public function attend(Request $request, AttentionRequest $attention): JsonResponse
    {
        if (! $attention->attended_at) {
            $attention->update(['attended_at' => now(), 'attended_by' => $request->user()->id]);
        }

        return response()->json(['result' => 'atendido']);
    }
}
