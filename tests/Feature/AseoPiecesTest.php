<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Booking\AseoReportService;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** El detalle de ropa a lavar muestra cada pieza del juego y las toallas por Playroom. */
class AseoPiecesTest extends TestCase
{
    private function row(string $roomName, string $category, int $roomId, int $guests): array
    {
        $room = new Room(['name' => $roomName]);
        $room->id = $roomId;
        $room->setRelation('category', new RoomCategory(['name' => $category]));

        return ['room' => $room, 'booking' => new Booking(['guests_count' => $guests])];
    }

    public function test_pieces_are_counted_per_room_with_towels_and_no_bed_for_go(): void
    {
        $pieces = app(AseoReportService::class)->piecesByRoom(collect([
            $this->row('PLUS 202', 'PLUS', 1, 2),
            $this->row('PLUS 202', 'PLUS', 1, 4),
            $this->row('GO 201', 'GO', 2, 2),
        ]));

        $go = $pieces->firstWhere('room.name', 'GO 201');
        $plus = $pieces->firstWhere('room.name', 'PLUS 202');

        $this->assertSame(0, $go['sets']);
        $this->assertSame(2, $go['total']);
        $this->assertSame(['Bajera' => 2, 'Encimera' => 2, 'Dúvet' => 2, 'Cabeceras' => 4], $plus['pieces']);
        $this->assertSame(6, $plus['towels']);
        $this->assertSame(16, $plus['total']);

        view()->share('errors', new ViewErrorBag);
        $html = view('partials.aseo-pieces', ['pieces' => $pieces])->render();
        $this->assertStringContainsString('Cabeceras', $html);
        $this->assertStringContainsString('Total piezas', $html);
    }
}
