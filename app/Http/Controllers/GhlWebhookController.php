<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Integrations\GhlMarketingConsent;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos que mandan los flujos de GHL al sistema. Se autentican con el
 * token GHL_INBOUND_SECRET en la URL del webhook (?token=...).
 */
class GhlWebhookController extends Controller
{
    /**
     * Baja de publicidad: el flujo de GHL lo llama cuando el cliente responde
     * "BAJA" (o pide no recibir más). Queda sin permiso en el sistema, así una
     * reserva futura no le vuelve a poner el tag de marketing.
     */
    public function marketingOptOut(Request $request, GhlMarketingConsent $consent): JsonResponse
    {
        $secret = (string) config('services.ghl.inbound_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->query('token'))) {
            return response()->json(['result' => 'no_autorizado'], 401);
        }

        $phone = (string) ($request->input('phone') ?? $request->input('contact.phone') ?? '');
        $customer = $phone !== '' ? Customer::where('phone_e164', Phone::toE164($phone))->first() : null;
        if (! $customer) {
            return response()->json(['result' => 'sin_cliente']);
        }

        $consent->change($customer, false, null, 'ghl');

        return response()->json(['result' => 'baja']);
    }
}
