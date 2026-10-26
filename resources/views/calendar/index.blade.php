<!doctype html><html lang="es" style="background:#111;color:#eee"><head><meta charset="utf-8"><meta name="color-scheme" content="dark"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calendario — HH Motel</title>
<style>
*{box-sizing:border-box}.calendar-page{max-width:1500px}.calendar-head,.calendar-toolbar{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:20px}.calendar-head h1{margin-bottom:4px}.primary-btn{background:#ff7918;color:white;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700}.calendar-toolbar,.calendar-card{background:#fff;border:1px solid #dededb;border-radius:14px;padding:16px}.calendar-views{display:flex;gap:5px}.calendar-views a,.calendar-nav a{color:#4c4c4a;text-decoration:none;padding:9px 16px;border-radius:8px}.calendar-views a.active,.calendar-views a:hover{background:#ff7918;color:#fff}.calendar-nav{display:flex;align-items:center;gap:16px}.calendar-nav a{font-size:25px;background:#f0f0ee}.weekdays,.month-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:7px}.weekdays{margin-bottom:7px;color:#777;font-size:11px;letter-spacing:.08em}.day-cell{min-height:130px;border:1px solid #e2e2df;border-radius:9px;padding:10px;background:#fff;position:relative}.day-cell.today{border:2px solid #ff7918;background:#fff8f2}.day-cell.today .day-create{background:#ff7918;color:#fff}.day-cell.muted{background:#f7f7f5;color:#aaa}.day-cell.past:not(.muted){background:#fafaf9}.day-cell.past:not(.muted) .day-create{color:#aaa}.day-create{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;border-radius:6px;text-decoration:inherit;color:inherit;font-weight:700;padding:0 4px}.today-tag{float:right;background:#ff7918;color:#fff;font-size:9px;font-weight:800;letter-spacing:.05em;padding:3px 7px;border-radius:20px}.event{display:block;text-decoration:none;color:#373737;background:#f8dfce;border-left:3px solid #ff7918;padding:5px 6px;margin-top:8px;border-radius:4px;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.event span{font-weight:700}.cat-go{border-color:#4b91d1}.cat-lite{border-color:#26b9b0}.cat-plus{border-color:#9b72d5}.cat-max{border-color:#e2aa32}.day-cell small{display:block;margin-top:7px;color:#777}.booking-row{display:grid;grid-template-columns:100px 1fr 170px 25px;align-items:center;gap:18px;padding:16px 8px;border-bottom:1px solid #e5e5e2;text-decoration:none;color:#272727}.booking-row:last-child{border:0}.booking-time span,.booking-row small{display:block;color:#777;font-size:12px;margin-top:4px}.booking-status{font-size:11px;text-transform:uppercase;color:#a55a13}.arrow{font-size:20px;color:#ff7918}.empty{color:#777;padding:30px}@media(max-width:700px){.calendar-head,.calendar-toolbar{align-items:flex-start;flex-direction:column}.calendar-toolbar{width:100%}.calendar-nav{width:100%;justify-content:space-between}.day-cell{min-height:95px;padding:6px}.event{font-size:10px;padding:3px}.weekdays{font-size:9px}.booking-row{grid-template-columns:65px 1fr 20px}.booking-status{display:none}.calendar-search{width:100%}.calendar-search input{flex:1;min-width:0}}
.calendar-search{display:flex;gap:8px}.calendar-search input{border:1px solid #dededb;border-radius:8px;padding:9px 12px;font-size:13px;min-width:240px}.calendar-search button{background:#ff7918;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-weight:600;cursor:pointer}
.calendar-history{margin-top:20px}.calendar-history h2{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#888;display:flex;align-items:baseline;gap:10px}
.hist-count{font-size:11px;text-transform:none;letter-spacing:0;color:#aaa;font-weight:500}
.calendar-history .booking-row{grid-template-columns:70px 1fr 170px 25px}
.calendar-history{max-height:420px;overflow-y:auto}
.calendar-split{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px}
@media(max-width:900px){.calendar-split{grid-template-columns:1fr}}
.calendar-split .calendar-history{margin-top:0}
.calendar-split .calendar-card.upcoming h2{color:#1c9169}
.calendar-split .calendar-card.past h2{color:#888}
.soon-stats{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px}.soon-stats span{background:#fff4ea;border:1px solid #ffd5b0;color:#9a4a00;border-radius:20px;padding:5px 12px;font-size:12px}.soon-stats b{font-size:15px;color:#c2410c;margin-right:3px}.soon-day{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;background:#f4f4f2;border-radius:6px;padding:5px 9px;margin:12px 0 2px}.soon-day:first-of-type{margin-top:4px}.calendar-split .calendar-card.upcoming h2{color:#c2410c}
</style>
</head><body>
@include('partials.navbar')
<main class="page-inner calendar-page">
<div class="calendar-head"><div><h1>Calendario de reservas</h1><p class="sub">Gestiona ocupación, llegadas y salidas por Playroom.</p></div>
 <form class="calendar-search" method="GET" action="{{ route('reservations.search') }}">
  <input type="text" name="q" placeholder="Buscar por código, nombre o teléfono…">
  <button type="submit">Buscar</button>
 </form>
</div>
<section class="calendar-toolbar">
 <div class="calendar-views"><a class="{{ $view==='month'?'active':'' }}" href="{{ route('calendar.index',['view'=>'month','date'=>$date->toDateString()]) }}">Mes</a><a class="{{ $view==='week'?'active':'' }}" href="{{ route('calendar.index',['view'=>'week','date'=>$date->toDateString()]) }}">Semana</a><a class="{{ $view==='day'?'active':'' }}" href="{{ route('calendar.index',['view'=>'day','date'=>$date->toDateString()]) }}">Día</a></div>
 <div class="calendar-nav"><a href="{{ route('calendar.index',['view'=>$view,'date'=>$previous]) }}">‹</a><strong>{{ $view==='month' ? ucfirst($date->locale('es')->isoFormat('MMMM YYYY')) : ($view==='week' ? 'Semana del '.$rangeStart->format('d/m').' al '.$rangeEnd->format('d/m/Y') : ucfirst($date->locale('es')->isoFormat('dddd D [de] MMMM YYYY'))) }}</strong><a href="{{ route('calendar.index',['view'=>$view,'date'=>$next]) }}">›</a></div>
</section>
@if($view==='month')
<section class="calendar-card">
 <div class="weekdays">
 @foreach(['LUN','MAR','MIÉ','JUE','VIE','SÁB','DOM'] as $day)<span>{{ $day }}</span>@endforeach
 </div>
 <div class="month-grid">
 @foreach($days as $day)
  <div class="day-cell {{ $day['date']->isToday() ? 'today' : '' }} {{ $day['date']->isPast() && ! $day['date']->isToday() ? 'past' : '' }} {{ $day['date']->month !== $date->month ? 'muted' : '' }}">
   <a class="day-create" href="{{ route('reservations.create', ['date' => $day['date']->toDateString()]) }}" title="Crear reserva para este día">{{ $day['date']->day }}</a>
   @if ($day['date']->isToday())<span class="today-tag">HOY</span>@endif
   @foreach($day['bookings']->take(4) as $booking)
    <a class="event cat-{{ strtolower($booking->room->category->name) }}" href="{{ route('reservations.show',$booking->code) }}"><span>{{ $booking->starts_at->format('H:i') }}</span> {{ $booking->room->name }}</a>
   @endforeach
   @if($day['bookings']->count() > 4)<small>+ {{ $day['bookings']->count()-4 }} más</small>@endif
  </div>
 @endforeach
 </div>
</section>
@endif
{{-- Para semana/día no hay un bloque de lista aparte -- sería la misma
     $bookings de "Historial" de más abajo, mostrada dos veces con menos
     información (sin fecha por fila, ambigua en vista semana). --}}

<div class="calendar-split">
 <section class="calendar-card upcoming calendar-history">
  <h2>◷ Próximas reservas <span class="hist-count">desde ahora, sin importar el período</span></h2>
  <div class="soon-stats">
   <span><b>{{ $soonStats['today'] }}</b> hoy</span>
   <span><b>{{ $soonStats['tomorrow'] }}</b> mañana</span>
   <span><b>{{ $soonStats['week'] }}</b> próx. 7 días</span>
   <span><b>{{ $soonStats['total'] }}</b> en total</span>
  </div>
  @forelse ($soon->groupBy(fn ($b) => $b->starts_at->toDateString()) as $day => $rows)
   @php $d = \Carbon\Carbon::parse($day); @endphp
   <div class="soon-day">{{ $d->isToday() ? 'Hoy' : ($d->isTomorrow() ? 'Mañana' : ucfirst($d->locale('es')->isoFormat('ddd D MMM'))) }} · {{ $rows->count() }} {{ Str::plural('reserva', $rows->count()) }}</div>
   @foreach ($rows as $booking)
    <a class="booking-row" href="{{ route('reservations.show',$booking->code) }}"><div class="booking-time"><b>{{ $booking->starts_at->format('H:i') }}</b><span>{{ \App\Support\TimeFormat::minutes($booking->duration_minutes) }}</span></div><div><strong>{{ $booking->room->name }}</strong><small>{{ $booking->room->category->name }} · {{ $booking->customer->name }}</small></div><div class="booking-status">{{ str_replace('_',' ', $booking->booking_status) }}</div><span class="arrow">→</span></a>
   @endforeach
  @empty
   <p class="empty">No hay reservas futuras por ahora.</p>
  @endforelse
 </section>
 <section class="calendar-card past calendar-history">
  <h2>✓ Historial {{ $view==='month' ? 'del mes' : ($view==='week' ? 'de la semana' : 'del día') }} <span class="hist-count">{{ $pastBookings->count() }} {{ Str::plural('reserva', $pastBookings->count()) }}</span></h2>
  @forelse ($pastBookings->sortByDesc('starts_at') as $booking)
   <a class="booking-row" href="{{ route('reservations.show',$booking->code) }}"><div class="booking-time"><b>{{ $booking->starts_at->locale('es')->isoFormat('D MMM') }}</b><span>{{ $booking->starts_at->format('H:i') }}</span></div><div><strong>{{ $booking->room->name }}</strong><small>{{ $booking->room->category->name }} · {{ $booking->customer->name }}</small></div><div class="booking-status">{{ str_replace('_',' ', $booking->booking_status) }}</div><span class="arrow">→</span></a>
  @empty
   <p class="empty">Todavía no pasó ninguna reserva en este período.</p>
  @endforelse
 </section>
</div>
</main>
</body></html>
