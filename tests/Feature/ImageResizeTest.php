<?php

namespace Tests\Feature;

use App\Support\Img;
use Tests\TestCase;

/** El redimensionador de fotos externas del catálogo solo acepta los hosts y anchos permitidos. */
class ImageResizeTest extends TestCase
{
    public function test_only_allowed_hosts_and_https_are_resized(): void
    {
        $this->get('/img?u='.urlencode('https://example.com/foto.jpg').'&w=320')->assertNotFound();
        $this->get('/img?u='.urlencode('http://assets.cdn.filesafe.space/foto.jpg').'&w=320')->assertNotFound();
        $this->get('/img')->assertNotFound();
    }

    public function test_img_helper_rewrites_only_external_catalog_images_and_snaps_widths(): void
    {
        $external = 'https://assets.cdn.filesafe.space/abc/media/foto.png';
        $this->assertStringContainsString('/img?', Img::url($external, 300));
        $this->assertStringContainsString('w=320', Img::url($external, 300));

        // Las fotos propias ya son livianas, y una URL de otro sitio no se toca.
        $this->assertSame('/images/rooms/101/1.jpg', Img::url('/images/rooms/101/1.jpg', 640));
        $this->assertSame('https://otro.com/a.jpg', Img::url('https://otro.com/a.jpg', 640));

        $this->assertSame(160, Img::snap(1));
        $this->assertSame(1280, Img::snap(5000));
        $this->assertSame(640, Img::snap(500));
    }
}
