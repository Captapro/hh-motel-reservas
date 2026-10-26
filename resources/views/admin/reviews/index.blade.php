@extends('admin.layout')
@section('title', 'Opiniones')
@section('content')
    <style>
        /* Esta página sola, no el resto de /admin -- el .wrap compartido
           (880px) deja mucho espacio muerto a la derecha con el widget y
           la tabla, que sí aprovechan bien una columna más ancha. */
        .wrap { max-width: 1400px !important; }
        .opiniones-grid { display:grid; grid-template-columns: 1.6fr 1fr; gap:16px; align-items:start; }
        @media (max-width: 900px) { .opiniones-grid { grid-template-columns: minmax(0, 1fr); } }
    </style>

    <div class="actions" style="justify-content:space-between; margin-bottom:18px;">
        <div><h1>Opiniones de huéspedes</h1><p class="sub">4-5 estrellas van directo a Google; 0-3 se quedan acá con el comentario.</p></div>
    </div>

    <div class="opiniones-grid">
        <div>
            <div class="card">
                <strong style="display:block; font-size:15px; margin-bottom:12px;">Puntuación real en Google</strong>
                @if ($google)
                    @php $fill = max(0, min(100, round($google['rating'] / 5 * 100))); @endphp
                    <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
                        <div style="font-size:44px; font-weight:800; line-height:1;">{{ number_format($google['rating'], 1, ',', '') }}</div>
                        <div>
                            <div style="font-size:24px; letter-spacing:2px; line-height:1.1; background:linear-gradient(90deg, #9fb93c {{ $fill }}%, #444 {{ $fill }}%); -webkit-background-clip:text; background-clip:text; color:transparent;" aria-label="{{ number_format($google['rating'], 1, ',', '') }} de 5 estrellas">★★★★★</div>
                            <div style="color:#aaa; font-size:14px; margin-top:2px;">{{ number_format($google['count'], 0, ',', '.') }} reseñas</div>
                        </div>
                    </div>
                @else
                    <p class="sub" style="margin:0;">No se pudo leer el puntaje de Google en este momento. Se vuelve a intentar solo en unos minutos.</p>
                @endif
            </div>

            <div class="card">
                <div style="max-height:320px; overflow-y:auto;">
                <table>
                    <thead><tr><th style="position:sticky; top:0; background:#1c1c1c;">Calificación</th><th style="position:sticky; top:0; background:#1c1c1c;">Reserva</th><th style="position:sticky; top:0; background:#1c1c1c;">Cliente</th><th style="position:sticky; top:0; background:#1c1c1c;">Comentario</th><th style="position:sticky; top:0; background:#1c1c1c;">Fecha</th></tr></thead>
                    <tbody>
                        @forelse ($reviews as $review)
                            <tr>
                                <td style="{{ $review->rating <= 3 ? 'color:#e88a9a;' : 'color:#6fd39a;' }} font-weight:700; white-space:nowrap;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</td>
                                <td>
                                    @if ($review->booking)
                                        <span class="pill">{{ $review->booking->code }}</span> {{ $review->booking->room->name }}
                                    @else
                                        <span class="sub">QR general</span>
                                    @endif
                                </td>
                                <td>{{ $review->booking?->customer?->name ?? '—' }}</td>
                                <td style="max-width:320px;">{{ $review->comment ?? '—' }}</td>
                                <td class="sub">{{ $review->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="color:#666;">Todavía no hay opiniones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div>
            <div class="card" style="display:flex; gap:26px; flex-wrap:wrap;">
                <div>
                    <div class="sub" style="margin-bottom:2px;">Promedio interno (nuestro filtro 1-5)</div>
                    <div style="font-size:22px; font-weight:800;">{{ $avgRating ?: '—' }} ★</div>
                </div>
                <div>
                    <div class="sub" style="margin-bottom:2px;">Calificaciones bajas (0-3)</div>
                    <div style="font-size:22px; font-weight:800; {{ $lowCount > 0 ? 'color:#e88a9a;' : '' }}">{{ $lowCount }}</div>
                </div>
            </div>

            <div class="card">
                <img src="{{ route('admin.reviews.qr_image') }}" alt="QR de opinión del huésped" style="width:100%; max-width:220px; height:auto; border-radius:8px; display:block; margin:0 auto 14px;">
                <strong style="display:block; font-size:15px; margin-bottom:4px;">QR para pedir la opinión del huésped</strong>
                <p class="sub" style="margin-bottom:10px;">Para pegar en recepción o entregar al huésped -- 4-5 estrellas van directo a Google, 0-3 quedan acá con el detalle.</p>
                <a class="link" href="{{ route('admin.reviews.qr_image') }}" target="_blank" rel="noopener">Descargar →</a>
                &nbsp;·&nbsp;
                <a class="link" href="{{ route('reviews.create') }}" target="_blank" rel="noopener">Ver página →</a>
            </div>

            @if (auth()->user()?->isAdministrador() && $reviews->isNotEmpty())
                <div class="card" style="border:1px solid #5a2327; background:#1d1315;">
                    <strong style="display:block; font-size:15px; margin-bottom:4px;">Borrar historial interno</strong>
                    <p class="sub" style="margin:0 0 10px;">Elimina las {{ $reviews->count() }} calificaciones internas que hay hoy (por ejemplo, las de prueba). No toca el puntaje de Google. No se puede deshacer.</p>
                    <form method="POST" action="{{ route('admin.reviews.destroy_all') }}" onsubmit="return confirm('¿Borrar todo el historial interno de calificaciones? No se puede deshacer.');">
                        @csrf @method('DELETE')
                        <label style="display:block; font-size:12.5px; color:#bbb; margin-bottom:6px;">Para confirmar, escribe <b>ELIMINAR</b></label>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <input name="confirm_text" autocomplete="off" placeholder="ELIMINAR" style="max-width:180px;">
                            <button type="submit" style="background:#b3202f; color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; cursor:pointer;">Borrar historial</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
