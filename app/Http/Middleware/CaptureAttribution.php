<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda en la sesión de dónde llegó el visitante al catálogo (parámetros utm,
 * fbclid de un anuncio de Meta o la página que lo mandó), para atribuirle la
 * reserva si termina reservando. Gana la última visita con origen conocido; una
 * navegación interna o sin datos no borra lo que ya había.
 */
class CaptureAttribution
{
    public const SESSION_KEY = 'marketing_attribution';

    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession()) {
            $this->capture($request);
        }

        return $next($request);
    }

    private function capture(Request $request): void
    {
        $utm = collect(self::UTM_KEYS)
            ->mapWithKeys(fn ($key) => [$key => mb_substr(trim((string) $request->query($key, '')), 0, 190)])
            ->filter()
            ->all();
        $fbclid = mb_substr(trim((string) $request->query('fbclid', '')), 0, 500);
        $referrer = (string) $request->headers->get('referer', '');
        $refHost = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        $external = $refHost !== '' && $refHost !== strtolower($request->getHost());

        if (! $utm && $fbclid === '' && ! $external) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, array_filter($utm + [
            'fbclid' => $fbclid,
            // Formato que pide Meta para el clic del anuncio: fb.1.<ms>.<fbclid>
            'fbc' => $fbclid !== '' ? 'fb.1.'.((int) floor(microtime(true) * 1000)).'.'.$fbclid : '',
            'referrer' => $external ? mb_substr($referrer, 0, 500) : '',
            'landing_url' => mb_substr($request->fullUrl(), 0, 500),
            'at' => now()->toIso8601String(),
        ]));
    }
}
