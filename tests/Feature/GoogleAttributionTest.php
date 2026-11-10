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
