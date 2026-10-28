<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Room;
use App\Models\RoomCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Número de WhatsApp del motel para el botón "Reservar" -- la reserva
     * real la sigue creando recepción, este catálogo es solo para que el
     * cliente vea las habitaciones y escriba.
     */
    public const WHATSAPP_NUMBER = '56977683108';

    public function index(): View
    {
        $today = Carbon::today();
        $catalogFallbackPhoto = Room::whereNotNull('photos')->orderBy('name')->get()
            ->flatMap(fn (Room $room) => $room->photos ?? [])
            ->filter()
            ->first();
        // Cupones con código (no las ofertas automáticas) que el cliente
        // puede autoaplicarse desde un banner -- la verificación de
        // identidad/edad la sigue haciendo recepción al check-in, así que
        // acá solo se filtra por vigencia de fecha.
        $coupons = Coupon::where('auto_apply', false)->where('is_active', true)
            ->get()
            ->filter(fn (Coupon $c) => (! $c->starts_at || $today->greaterThanOrEqualTo($c->starts_at)) && (! $c->ends_at || $today->lessThanOrEqualTo($c->ends_at)))
            ->map(function (Coupon $c) use ($catalogFallbackPhoto) {
                $benefit = $c->discount_type === 'percentage'
                    ? '-'.$c->discount_value.'%'
                    : '-$'.number_format($c->discount_value, 0, ',', '.');
                $hints = array_filter([
                    $c->constraintsLabel(),
                    $c->min_age ? $c->min_age.'+ años' : null,
                    $c->requires_verification ? 'pide verificar en recepción' : null,
                ]);

                return [
                    'code' => $c->code,
                    'name' => $c->internal_name,
                    'image' => $c->image_url ?: $catalogFallbackPhoto,
                    'benefit' => $benefit,
                    'hint' => implode(' · ', $hints),
                    'needsBirthDate' => (bool) $c->min_age,
                ];
            })
            ->values();

        $liveOffers = Coupon::offers()->where('is_active', true)
            ->with(['rooms:id,name,room_category_id,photos', 'roomCategories:id'])
            ->get()
            ->filter(fn (Coupon $offer) => (! $offer->starts_at || $today->greaterThanOrEqualTo($offer->starts_at)) && (! $offer->ends_at || $today->lessThanOrEqualTo($offer->ends_at)));
        $categories = RoomCategory::where('is_active', true)
            ->orderBy('display_order')
            ->with([
                'rooms',
                'rateRulePrices' => fn ($q) => $q->whereHas('rateRule', fn ($r) => $r->where('is_active', true))->with('rateRule'),
            ])
            ->get()
            ->map(function (RoomCategory $category) use ($liveOffers) {
                $photos = $category->rooms->flatMap(fn ($room) => $room->photos ?? [])->unique()->values();
                $videos = $category->rooms->flatMap(fn ($room) => $room->videos ?? [])->unique()->values();

                $prices = $this->pricesFromRows($category->rateRulePrices);
                $offer = $liveOffers->first(function (Coupon $offer) use ($category) {
                    $categoryIds = $offer->roomCategories->pluck('id');
                    $roomCategoryIds = $offer->rooms->pluck('room_category_id');

                    return ($categoryIds->isEmpty() && $roomCategoryIds->isEmpty()) || $categoryIds->contains($category->id) || $roomCategoryIds->contains($category->id);
                });
                // Precio de referencia para mostrar "antes/ahora" en la
                // oferta -- la duración más corta (la primera de la tabla),
                // mismo criterio que ya se usa para armar esa tabla.
                $referencePrice = $prices->first()['hh'] ?? null;
                $offerPrice = $referencePrice === null ? null : match ($offer?->discount_type) {
                    'percentage' => (int) round($referencePrice * (1 - $offer->discount_value / 100)),
                    'precio_fijo' => (int) $offer->discount_value,
                    default => null,
                };

                return [
                    'category' => $category,
                    'photos' => $photos,
                    'videos' => $videos,
                    'prices' => $prices,
                    'offer' => $offer ? [
                        'label' => $offer->discount_type === 'percentage' ? $offer->discount_value.'% de descuento' : 'Desde $'.number_format($offer->discount_value, 0, ',', '.'),
                        'name' => $offer->internal_name,
                        'originalPrice' => $offerPrice !== null ? $referencePrice : null,
                        'offerPrice' => $offerPrice,
                        'durationLabel' => $prices->first() ? ($prices->first()['duration'] >= 60 ? intdiv($prices->first()['duration'], 60).' h' : $prices->first()['duration'].' min') : null,
                        'rooms' => $offer->rooms->filter(fn ($room) => $room->room_category_id === $category->id)->map(fn ($room) => ['id' => $room->id, 'name' => $room->name, 'photo' => collect($room->photos ?? [])->first() ?: $photos->first()])->values()->all(),
                    ] : null,
                    'salesTip' => $category->sales_tip ?: 'Conoce esta experiencia HH.',
                    'whatsappUrl' => 'https://wa.me/'.self::WHATSAPP_NUMBER.'?text='.rawurlencode("Hola! Tengo una duda sobre el Playroom {$category->name} en HH."),
                ];
            });

        return view('catalog.index', [
            'categories' => $categories,
            'coupons' => $coupons,
            'whatsappUrl' => 'https://wa.me/'.self::WHATSAPP_NUMBER.'?text='.rawurlencode('Hola! Quiero reservar un Playroom en HH.'),
        ]);
    }

    /**
     * Ficha pública de una habitación puntual (no toda la categoría) --
     * pensada para mandar por WhatsApp a un cliente que pregunta por ESA
     * pieza específica, con sus propias fotos/videos.
     */
    public function room(Room $room): View
    {
        $room->load('category', 'furniture');

        $prices = $this->pricesFromRows(
            $room->category->rateRulePrices()->whereHas('rateRule', fn ($r) => $r->where('is_active', true))->with('rateRule')->get()
        );

        // Misma oferta que se ve en el catálogo general, pero mirando esta
        // habitación puntual -- offerLiveFor ya filtra por fecha/día/alcance
        // (rooms o categoría), acá solo se le suma el precio antes/después.
        $today = Carbon::today();
        $offerCoupon = Coupon::offers()->where('is_active', true)
            ->with(['rooms:id', 'roomCategories:id'])
            ->get()
            ->first(fn (Coupon $c) => $c->offerLiveFor($room, $today));
        $referencePrice = $prices->first()['hh'] ?? null;
        $offer = null;
        if ($offerCoupon && $referencePrice !== null) {
            $offerPrice = match ($offerCoupon->discount_type) {
                'percentage' => (int) round($referencePrice * (1 - $offerCoupon->discount_value / 100)),
                'precio_fijo' => (int) $offerCoupon->discount_value,
                default => null,
            };
            if ($offerPrice !== null) {
                $offer = [
                    'name' => $offerCoupon->internal_name,
                    'originalPrice' => $referencePrice,
                    'offerPrice' => $offerPrice,
                    'savePct' => $referencePrice > 0 ? round((1 - $offerPrice / $referencePrice) * 100) : 0,
                    'durationLabel' => $prices->first() ? ($prices->first()['duration'] >= 60 ? intdiv($prices->first()['duration'], 60).' h' : $prices->first()['duration'].' min') : null,
                ];
            }
        }

        return view('catalog.room', [
            'room' => $room,
            'category' => $room->category,
            'salesTip' => $room->category->sales_tip,
            'photos' => collect($room->photos ?? [])->values(),
            'videos' => collect($room->videos ?? [])->values(),
            // Solo lo operativo -- si algo está en reparación o fuera de uso
            // no se le puede ofrecer al cliente, aunque siga cargado acá.
            'equipment' => $room->furniture->where('pivot.condition', 'operativo')->values(),
            'prices' => $prices,
            'offer' => $offer,
        ]);
    }

    private function pricesFromRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy('duration_minutes')
            ->map(fn ($rows, $duration) => [
                'duration' => (int) $duration,
                'hh' => $rows->first(fn ($p) => $p->rateRule->name === 'HH')?->price,
                'hot' => $rows->first(fn ($p) => $p->rateRule->name === 'HOT')?->price,
            ])
            ->sortBy('duration')
            ->values();
    }
}
