<?php

namespace App\Services\Marketing;

use App\Http\Middleware\CaptureAttribution;
use App\Models\Booking;
use App\Models\TrackingData;
use Illuminate\Http\Request;
use Throwable;

/**
 * Atribución de cada reserva: qué canal la trajo. Las del catálogo usan lo que
 * guardó CaptureAttribution; las de recepción son "Presencial" o "Reserva asistida".
 */
class Attribution
{
    /** Guarda el origen de una reserva recién creada desde el catálogo. Nunca lanza. */
    public static function recordForBooking(Booking $booking, Request $request): void
    {
        try {
            $data = (array) $request->session()->get(CaptureAttribution::SESSION_KEY, []);
            $fbc = ($data['fbc'] ?? null) ?: $request->cookie('_fbc');
            $fbp = $request->cookie('_fbp');

            $tracking = new TrackingData([
                'booking_id' => $booking->id,
                'session_id' => $request->session()->getId(),
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'utm_content' => $data['utm_content'] ?? null,
                'utm_term' => $data['utm_term'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'landing_url' => $data['landing_url'] ?? null,
                'fbclid' => $data['fbclid'] ?? null,
                'gclid' => $data['gclid'] ?? null,
                'gbraid' => $data['gbraid'] ?? null,
                'wbraid' => $data['wbraid'] ?? null,
                'fbc' => $fbc ? mb_substr((string) $fbc, 0, 255) : null,
                'fbp' => $fbp ? mb_substr((string) $fbp, 0, 255) : null,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
            $tracking->source_label = self::labelFor($tracking);
            $tracking->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Nombre corto del canal; es también la "fuente" de la oportunidad en GHL. */
    public static function labelFor(?TrackingData $t): string
    {
        if (! $t) {
            return 'Directo';
        }

        $source = strtolower(trim((string) $t->utm_source));
        if ($source !== '') {
            $name = match (true) {
                in_array($source, ['ig', 'instagram'], true) => 'Instagram',
                in_array($source, ['fb', 'facebook'], true) => 'Facebook',
                in_array($source, ['meta', 'facebook_ads', 'fb_ads', 'meta_ads'], true) => 'Meta Ads',
                in_array($source, ['google', 'gads'], true) => 'Google',
                in_array($source, ['wa', 'whatsapp'], true) => 'WhatsApp',
                in_array($source, ['ghl', 'hhmotel', 'web', 'sitio'], true) => 'Sitio hhmotel.cl',
                $source === 'tv' => 'Pantalla TV',
                default => ucfirst($source),
            };

            return mb_substr($name.($t->utm_campaign ? ' · '.$t->utm_campaign : ''), 0, 120);
        }

        if ($t->fbclid) {
            return 'Meta Ads';
        }

        if ($t->gclid || $t->gbraid || $t->wbraid) {
            return 'Google Ads';
        }

        $host = strtolower((string) parse_url((string) $t->referrer, PHP_URL_HOST));

        return match (true) {
            $host === '' => 'Directo',
            str_contains($host, 'instagram') => 'Instagram',
            str_contains($host, 'facebook') || $host === 'fb.com' || str_ends_with($host, '.fb.com') => 'Facebook',
            str_contains($host, 'google') => 'Google',
            str_contains($host, 'whatsapp') || $host === 'wa.me' => 'WhatsApp',
            str_contains($host, 'hhmotel.cl') => 'Sitio hhmotel.cl',
            default => mb_substr((string) preg_replace('/^www\./', '', $host), 0, 120),
        };
    }

    /** Canal de cualquier reserva (catálogo o recepción). */
    public static function sourceOf(Booking $booking): string
    {
        if ($booking->origin !== 'catalogo') {
            return $booking->originLabel();
        }

        return $booking->trackingData?->source_label ?: 'Directo';
    }
}
