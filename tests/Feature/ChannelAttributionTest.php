<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TrackingData;
use App\Services\Marketing\Attribution;
use Tests\TestCase;

/**
 * En la tabla de canales de Analytics, una venta de recepción no tiene gclid
 * ni utm: lo único que se sabe de su origen es lo que el cliente contestó al
 * llegar. Antes eso se perdía dentro de «Presencial» y la publicidad parecía
 * vender mucho menos de lo que vende. Ahora cada respuesta tiene su propia
 * fila, separada de lo que el sistema sí midió.
 */
class ChannelAttributionTest extends TestCase
{
    private function booking(string $origin, ?string $heardFrom, ?string $sourceLabel = null): Booking
    {
        $booking = new Booking;
        $booking->forceFill(['origin' => $origin, 'heard_from' => $heardFrom]);
        $booking->setRelation('adOrigin', null);
        $booking->setRelation('trackingData', $sourceLabel ? new TrackingData(['source_label' => $sourceLabel]) : null);

        return $booking;
    }

    public function test_a_walk_in_that_said_google_gets_its_own_channel(): void
    {
        $this->assertSame('Google · dijo en recepción', Attribution::channelOf($this->booking('presencial', 'google'))[0]);
        $this->assertSame('Instagram · dijo en recepción', Attribution::channelOf($this->booking('recepcion', 'instagram'))[0]);
        $this->assertSame('Recomendado por un amigo', Attribution::channelOf($this->booking('presencial', 'amigo'))[0]);
        $this->assertSame('Pasaba por fuera', Attribution::channelOf($this->booking('presencial', 'paso'))[0]);
    }

    public function test_an_answer_that_says_nothing_stays_in_presencial(): void
    {
        $this->assertSame('Presencial', Attribution::channelOf($this->booking('presencial', 'otro'))[0]);
        $this->assertSame('Presencial', Attribution::channelOf($this->booking('presencial', null))[0]);
    }

    public function test_what_the_system_measured_is_not_overwritten_by_what_the_client_said(): void
    {
        $medido = $this->booking('catalogo', 'google', 'Google · gads_busqueda_motel');

        $this->assertSame('Google · gads_busqueda_motel', Attribution::channelOf($medido)[0]);
    }
}
