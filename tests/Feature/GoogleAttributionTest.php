<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureAttribution;
use App\Models\TrackingData;
use App\Services\Marketing\Attribution;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Origen Google: la ficha de Maps y los anuncios de Google Ads llegan con utm; los
 * anuncios además traen gclid (o gbraid/wbraid en iOS), que se guarda para poder
 * subir las reservas reales como conversiones.
 */
class GoogleAttributionTest extends TestCase
{
    private function visit(string $url): array
    {
        $session = app('session')->driver('array');
        $request = Request::create($url, 'GET');
        $request->setLaravelSession($session);
        (new CaptureAttribution)->handle($request, fn () => response('ok'));

        return (array) $session->get(CaptureAttribution::SESSION_KEY, []);
    }

    public function test_google_ads_click_ids_and_utm_are_captured(): void
    {
        $data = $this->visit('https://app.hhmotel.cl/catalogo?utm_source=google&utm_medium=cpc&utm_campaign=gads_busqueda_motel&gclid=Cj0KCQ123');
        $this->assertSame('google', $data['utm_source']);
        $this->assertSame('gads_busqueda_motel', $data['utm_campaign']);
        $this->assertSame('Cj0KCQ123', $data['gclid']);

        $ios = $this->visit('https://app.hhmotel.cl/catalogo?gbraid=0AAAA');
        $this->assertSame('0AAAA', $ios['gbraid']);
    }

    public function test_own_website_return_does_not_erase_an_existing_google_click(): void
    {
        $session = app('session')->driver('array');
        foreach ([
            ['https://app.hhmotel.cl/catalogo?gclid=CLICK-ORIGINAL', 'https://www.google.com/'],
            ['https://app.hhmotel.cl/catalogo/reservar', 'https://hhmotel.cl/playrooms'],
        ] as [$url, $referrer]) {
            $request = Request::create($url, 'GET', server: ['HTTP_REFERER' => $referrer]);
            $request->setLaravelSession($session);
            (new CaptureAttribution)->handle($request, fn () => response('ok'));
        }
        $this->assertSame('CLICK-ORIGINAL', $session->get(CaptureAttribution::SESSION_KEY)['gclid'] ?? null);
    }

    public function test_first_visit_from_own_website_is_still_recorded(): void
    {
        $session = app('session')->driver('array');
        $request = Request::create('https://app.hhmotel.cl/catalogo', 'GET', server: ['HTTP_REFERER' => 'https://www.hhmotel.cl/playrooms']);
        $request->setLaravelSession($session);
        (new CaptureAttribution)->handle($request, fn () => response('ok'));
        $this->assertSame('https://www.hhmotel.cl/playrooms', $session->get(CaptureAttribution::SESSION_KEY)['referrer']);
    }

    public function test_a_new_explicit_campaign_from_own_site_replaces_the_old_attribution(): void
    {
        $session = app('session')->driver('array');
        $session->put(CaptureAttribution::SESSION_KEY, ['gclid' => 'OLD']);
        $request = Request::create('https://app.hhmotel.cl/catalogo?gclid=NEW', 'GET', server: ['HTTP_REFERER' => 'https://hhmotel.cl/']);
        $request->setLaravelSession($session);
        (new CaptureAttribution)->handle($request, fn () => response('ok'));
        $this->assertSame('NEW', $session->get(CaptureAttribution::SESSION_KEY)['gclid']);
    }

    public function test_labels_separate_the_maps_listing_from_ads_campaigns(): void
    {
        $this->assertSame('Google · ficha_maps', Attribution::labelFor(new TrackingData(['utm_source' => 'google', 'utm_medium' => 'organic', 'utm_campaign' => 'ficha_maps'])));
        $this->assertSame('Google · gads_busqueda_motel', Attribution::labelFor(new TrackingData(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'gads_busqueda_motel'])));
        // Un clic de Google Ads sin utm igual se reconoce.
        $this->assertSame('Google Ads', Attribution::labelFor(new TrackingData(['gclid' => 'abc'])));
        $this->assertSame('Google Ads', Attribution::labelFor(new TrackingData(['wbraid' => 'abc'])));
    }

    public function test_the_google_tags_only_render_when_configured(): void
    {
        config(['services.google.tag_id' => null, 'services.google.analytics_id' => '']);
        $this->assertStringNotContainsString('googletagmanager', view('catalog._google-tag')->render());

        config(['services.google.tag_id' => 'AW-123456789', 'services.google.analytics_id' => '']);
        $html = view('catalog._google-tag')->render();
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=AW-123456789', $html);
        $this->assertStringContainsString("gtag('config', \"AW-123456789\")", $html);
        $this->assertStringNotContainsString('linker', $html);
    }

    public function test_analytics_loads_with_ads_and_links_both_domains(): void
    {
        config(['services.google.tag_id' => 'AW-123456789', 'services.google.analytics_id' => 'G-TEST12345']);
        $html = view('catalog._google-tag')->render();
        $this->assertSame(1, substr_count($html, 'googletagmanager.com/gtag/js'));
        $this->assertStringContainsString("gtag('config', \"AW-123456789\")", $html);
        $this->assertStringContainsString("gtag('config', \"G-TEST12345\"", $html);
        $this->assertStringContainsString('hhmotel.cl', $html);
        $this->assertStringContainsString('app.hhmotel.cl', $html);
    }
}
