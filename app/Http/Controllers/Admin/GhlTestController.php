<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ExpirarReservasPendientes;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Integrations\GhlClient;
use App\Services\Integrations\GhlOpportunitySync;
use App\Services\Integrations\GhlReviewRequests;
use App\Services\Marketing\MetaConversions;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * Pantalla de diagnóstico para probar la conexión con GHL contra un
 * contacto real (de prueba) antes de confiar en que la sincronización
 * automática de reservas (GhlBookingSync) funciona -- sin esto, la única
 * forma de saber si el token/location ID están bien sería esperar a que
 * entre una reserva real y revisar los logs.
 */
class GhlTestController extends Controller
{
    public function index(GhlClient $client, GhlReviewRequests $reviews): View
    {
        return view('admin.ghl.index', ['configured' => $client->isConfigured()] + $this->reviewDiagnostics($reviews));
    }

    /**
     * Pedidos de opinión: si los webhooks están puestos (sin mostrar la URL), si
     * el proceso automático está corriendo, el último error de cada paso, el último
     * envío a GHL y qué pasó con cada salida de las últimas 24 horas.
     */
    private function reviewDiagnostics(GhlReviewRequests $reviews): array
    {
        $lastRun = Cache::get(ExpirarReservasPendientes::LAST_RUN_KEY);
        $stepErrors = collect(ExpirarReservasPendientes::STEPS)
            ->mapWithKeys(fn ($step) => [$step => Cache::get(ExpirarReservasPendientes::ERROR_KEY_PREFIX.$step)])
            ->filter();

        $checkouts = Booking::with(['customer', 'room'])
            ->where('booking_status', 'FINALIZADA')
            ->where('checked_out_at', '>=', now()->subDay())
            ->orderByDesc('checked_out_at')
            ->get()
            ->map(function (Booking $booking) use ($reviews) {
                $hasPhone = filled($booking->customer?->phone_e164);
                $result = Cache::get(GhlReviewRequests::RESULT_KEY_PREFIX.$booking->id);
                $status = match (true) {
                    $result && $result['sent'] => 'Enviado a GHL ('.$result['result'].')',
                    $result && $booking->review_requested_at => 'Falló, ya no se reintenta ('.$result['result'].')',
                    (bool) $result => 'Falló, se reintenta ('.$result['result'].')',
                    ! $booking->review_requested_at && $booking->checked_out_at->gt(now()->subMinutes(GhlReviewRequests::MINUTES_AFTER_CHECKOUT)) => 'Esperando sus 10 minutos',
                    $reviews->alreadyReviewed($booking) => 'Ya había opinado: no se envía',
                    ! $hasPhone => 'Sin teléfono: no se envía',
                    $reviews->optedOut($booking) => 'Se dio de baja de promociones: no se envía',
                    (bool) $booking->review_requested_at => 'Enviado a GHL',
                    default => 'Por enviar',
                };

                return ['booking' => $booking, 'has_phone' => $hasPhone, 'status' => $status];
            });

        return [
            'reviewWebhooks' => [
                'GHL_WEBHOOK_PEDIR_OPINION' => filled(config('services.ghl.webhook_pedir_opinion')),
                'GHL_WEBHOOK_CUPON_OPINION' => filled(config('services.ghl.webhook_cupon_opinion')),
            ],
            'jobLastRun' => $lastRun ? Carbon::parse($lastRun) : null,
            'jobErrors' => $stepErrors,
            'lastReviewSent' => Cache::get(GhlReviewRequests::LAST_SENT_KEY_PREFIX.'pedir_opinion'),
            'recentCheckouts' => $checkouts,
            'funnel' => [
                'since' => config('services.ghl.opportunities_since'),
                'synced' => Booking::whereNotNull('ghl_opportunity_id')->count(),
                'pending' => GhlOpportunitySync::pendingQuery()->count(),
                'lastRun' => Cache::get(GhlOpportunitySync::LAST_RUN_KEY),
                'error' => Cache::get(GhlOpportunitySync::ERROR_KEY),
                'badPhones' => Customer::whereNotNull('phone_e164')->where('phone_e164', '!=', '')->orderByDesc('id')->get(['id', 'name', 'phone_e164'])->filter(fn (Customer $c) => Phone::problem($c->phone_e164) !== null)->take(30)->values(),
                'failed' => Booking::whereNotNull('ghl_sync_failed_at')->orderBy('id')->get(['code', 'ghl_sync_error', 'ghl_sync_attempts', 'ghl_sync_failed_at']),
            ],
            'meta' => [
                'pixel' => filled(config('services.meta.pixel_id')),
                'capi' => MetaConversions::enabled(),
                'sent' => Booking::whereNotNull('meta_purchase_sent_at')->count(),
                'past' => MetaConversions::pastPurchasesQuery(Carbon::parse(self::PAST_SALES_SINCE, 'America/Santiago'))->count(),
                'pastWithContact' => MetaConversions::pastPurchasesQuery(Carbon::parse(self::PAST_SALES_SINCE, 'America/Santiago'))
                    ->whereHas('customer', fn ($q) => $q->where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('phone_e164')->where('phone_e164', '!=', ''))->orWhere(fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))))->count(),
                'last' => Cache::get(MetaConversions::LAST_RESULT_KEY),
            ],
        ];
    }

    /** Desde aquí el sistema se usa en recepción: antes las ventas vivían en planillas sin teléfono. */
    private const PAST_SALES_SINCE = '2026-11-01';

    /** Envío único de las ventas pagadas que quedaron sin avisar a Meta. */
    public function sendPastSalesToMeta(MetaConversions $meta): RedirectResponse
    {
        $result = $meta->sendPastPurchases(Carbon::parse(self::PAST_SALES_SINCE, 'America/Santiago'));
        AuditLog::record(auth()->id(), 'meta.ventas_pasadas', 'Booking', 0, null, $result);

        return redirect()->route('admin.ghl.index')->with('status', $result['error']
            ? 'No se pudieron enviar: '.$result['error'].($result['sent'] ? " (alcanzaron a salir {$result['sent']})" : '')
            : "Listo: {$result['sent']} ventas enviadas a Meta. {$result['skipped']} sin teléfono ni correo no se pueden enviar.");
    }

    public function test(Request $request, GhlClient $client, GhlReviewRequests $reviews): View
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $phone = Phone::toE164($validated['phone']);
        $log = [];
        $error = null;

        try {
            $log[] = "Buscando contacto existente con teléfono {$phone}...";
            $existingId = $client->findContactIdByPhone($phone);
            $log[] = $existingId ? "Encontrado: {$existingId}" : 'No existía -- se va a crear uno nuevo.';

            $contactId = $client->upsertContact($phone, $validated['name']);
            $log[] = "Contacto en GHL: {$contactId}";

            $tag = GhlBookingSync::TAG_PREFIX.now()->timezone('America/Santiago')->toDateString();
            $client->replaceTagWithPrefix($contactId, GhlBookingSync::TAG_PREFIX, $tag);
            $log[] = "Tag aplicado: {$tag}";

            $tags = $client->getTags($contactId);
            $log[] = 'Tags actuales del contacto: '.(empty($tags) ? '(ninguno)' : implode(', ', $tags));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.ghl.index', [
            'configured' => $client->isConfigured(),
            'log' => $log,
            'error' => $error,
            'testedPhone' => $phone,
        ] + $this->reviewDiagnostics($reviews));
    }
}
