<?php

namespace App\Http\Controllers;

use App\Models\AttentionRequest;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Traspaso a una persona: el flujo de GHL avisa cuando Cata deriva un chat, y
 * recepción ve el contacto en todas sus pantallas hasta marcarlo atendido.
 */
class AttentionRequestController extends Controller
{
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

    public function pending(Request $request): JsonResponse
    {
        // Quien tiene Conversaciones atiende ahí; el resto sigue yendo a WhatsApp.
        $inbox = $request->user()->canAccess('conversaciones');

        return response()->json(AttentionRequest::open()->orderBy('created_at')->limit(10)->get()->map(fn (AttentionRequest $a) => [
            'id' => $a->id,
            'name' => $a->name ?: $a->phone,
            'phone' => $a->phone,
            'reason' => $a->reason,
            'whatsapp' => $a->whatsappUrl(),
            'conversation' => $inbox ? route('admin.conversations.index', ['aviso' => $a->id]) : null,
            'since' => $a->created_at->copy()->timezone('America/Santiago')->format('H:i'),
            'attend_url' => route('attention.attend', $a),
        ])->values());
    }

    public function attend(Request $request, AttentionRequest $attention): JsonResponse
    {
        if (! $attention->attended_at) {
            $attention->update(['attended_at' => now(), 'attended_by' => $request->user()->id]);
        }

        return response()->json(['result' => 'atendido']);
    }
}
