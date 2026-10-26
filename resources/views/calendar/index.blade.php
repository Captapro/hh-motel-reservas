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

/* ===== Versión comprimida: menos aire, más calendario en pantalla ===== */
.calendar-page{padding-top:14px}
.calendar-head,.calendar-toolbar{gap:12px;margin-bottom:10px}
.calendar-head h1{font-size:20px;margin-bottom:0}.calendar-head .sub{margin:2px 0 0;font-size:12.5px}
.calendar-search input{padding:7px 10px;min-width:220px}.calendar-search button{padding:7px 14px}
.calendar-toolbar,.calendar-card{padding:10px 12px;border-radius:12px}
.calendar-views a,.calendar-nav a{padding:6px 13px}.calendar-nav{gap:10px}.calendar-nav a{font-size:20px;line-height:1;padding:5px 12px}
.weekdays,.month-grid{gap:5px}.weekdays{margin-bottom:5px}
.day-cell{min-height:92px;padding:6px 7px;border-radius:8px}
.day-create{min-width:20px;height:20px;font-size:12.5px}.today-tag{font-size:8.5px;padding:2px 6px}
.event{padding:3px 5px;margin-top:4px;font-size:11px;line-height:1.3}
.day-cell small{margin-top:4px;font-size:11px}
.booking-row{padding:8px 6px;gap:12px}
.booking-time span,.booking-row small{margin-top:1px;font-size:11.5px}
.calendar-split{gap:12px;margin-top:12px}.calendar-history{margin-top:12px;max-height:340px}
.calendar-history h2{margin-bottom:6px}
.soon-stats{margin-bottom:6px}.soon-stats span{padding:3px 10px;font-size:11.5px}.soon-stats b{font-size:13.5px}
.soon-day{margin:8px 0 0;padding:3px 8px}
.empty{padding:14px 8px;font-size:13.5px}
@media(max-width:700px){.day-cell{min-height:74px;padding:4px}.event{font-size:9.5px;padding:2px 3px;margin-top:3px}}

html{background:#f5f5f4 !important}body{min-height:100vh}
.calendar-split-one{grid-template-columns:1fr}
.calendar-history{max-height:300px}
.sheet-card{margin-top:12px}
.sheet-card h2{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.sheet-wrap{overflow:auto;max-height:calc(100vh - 290px);min-height:240px;border:1px solid #e5e7eb;border-radius:8px}
table.sheet{width:100%;border-collapse:collapse;font-size:12.5px}
table.sheet th{position:sticky;top:0;z-index:1;background:#f3f4f6;color:#4b5563;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:8px 9px;border:1px solid #e5e7eb;white-space:nowrap}
table.sheet td{padding:6px 9px;border:1px solid #e5e7eb;white-space:nowrap;background:#fff;color:#1f2937}
table.sheet th.n,table.sheet td.n{text-align:right;font-variant-numeric:tabular-nums}
table.sheet tbody tr[data-href]{cursor:pointer}
table.sheet tbody tr[data-href]:hover td{background:#fff7ed}
table.sheet a.code{font-family:ui-monospace,monospace;color:#c2410c;text-decoration:none;font-weight:600}
table.sheet td.owe{color:#dc2626;font-weight:700}
table.sheet tr.zero td{color:#9ca3af;text-align:center;padding:14px}
table.sheet tr.total td{background:#f3f4f6;font-weight:700;border-top:2px solid #d1d5db;position:sticky;bottom:0}
.st{font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:20px;background:#f3f4f6;color:#4b5563}
.st-finalizada{background:#eff6ff;color:#1d4ed8}.st-confirmada,.st-check_in{background:#fff7ed;color:#c2410c}.st-pendiente_pago{background:#fffbeb;color:#b45309}
.hist-period{display:flex;align-items:center;gap:6px;margin:0 0 10px;font-size:12.5px;color:#6b7280}.hist-period a{color:#4c4c4a;text-decoration:none;padding:5px 12px;border-radius:999px;background:#fff;border:1px solid #dededb}.hist-period a.active,.hist-period a:hover{background:#ff7918;border-color:#ff7918;color:#fff}
.day-list{margin-top:12px}.day-list h2{margin:0 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;display:flex;align-items:baseline;gap:10px}.day-list .booking-row{grid-template-columns:90px 1fr 150px 20px}
.org{font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:20px;background:#f3f4f6;color:#6b7280;white-space:nowrap}.org-presencial{background:#fff7ed;color:#c2410c}.org-catalogo{background:#eff6ff;color:#1d4ed8}.org-recepcion{background:#f5f3ff;color:#6d28d9}
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
 <div class="calendar-views"><a class="{{ $view==='month'?'active':'' }}" href="{{ route('calendar.index',['view'=>'month','date'=>$date->toDateString()]) }}">Mes</a><a class="{{ $view==='week'?'active':'' }}" href="{{ route('calendar.index',['view'=>'week','date'=>$date->toDateString()]) }}">Semana</a><a class="{{ $view==='day'?'active':'' }}" href="{{ route('calendar.index',['view'=>'day','date'=>$date->toDateString()]) }}">Día</a><a class="{{ $view==='history'?'active':'' }}" href="{{ route('calendar.index',['view'=>'history','date'=>$date->toDateString()]) }}">Historial</a></div>
 <div class="calendar-nav"><a href="{{ route('calendar.index',['view'=>$view,'date'=>$previous,'periodo'=>$view==='history'?$period:null]) }}">‹</a><strong>{{ $period==='month' ? ucfirst($date->locale('es')->isoFormat('MMMM YYYY')) : ($period==='week' ? 'Semana del '.$rangeStart->format('d/m').' al '.$rangeEnd->format('d/m/Y') : ucfirst($date->locale('es')->isoFormat('dddd D [de] MMMM YYYY'))) }}</strong><a href="{{ route('calendar.index',['view'=>$view,'date'=>$next,'periodo'=>$view==='history'?$period:null]) }}">›</a></div>
</section>
@if($view==='history')
<div class="hist-period"><span>Ver por:</span>
 @foreach(['day'=>'Día','week'=>'Semana','month'=>'Mes'] as $pk => $pl)
  <a class="{{ $period===$pk?'active':'' }}" href="{{ route('calendar.index',['view'=>'history','date'=>$date->toDateString(),'periodo'=>$pk]) }}">{{ $pl }}</a>
 @endforeach
</div>
@endif
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

@if($view==='week')
<section class="calendar-card day-list">
 <h2>{{ $view==='week' ? 'Reservas de la semana' : 'Reservas del día' }} <span class="hist-count">{{ $bookings->count() }} {{ Str::plural('reserva', $bookings->count()) }}</span></h2>
 @forelse ($bookings as $booking)
  <a class="booking-row" href="{{ route('reservations.show',$booking->code) }}">
   <div class="booking-time"><b>{{ $booking->starts_at->format('H:i') }}</b><span>{{ $view==='week' ? $booking->starts_at->locale('es')->isoFormat('ddd D MMM') : \App\Support\TimeFormat::minutes($booking->duration_minutes) }}</span></div>
   <div><b>{{ $booking->room->name }}</b> <small style="display:inline">· {{ $booking->room->category->name }}</small><small>{{ $booking->customer->name }} · {{ $booking->code }}</small></div>
   <div class="booking-status">{{ str_replace('_', ' ', $booking->booking_status) }}</div>
   <span class="arrow">›</span>
  </a>
 @empty
  <p class="empty">No hay reservas en este período.</p>
 @endforelse
</section>
@endif
@if($view==='day')
@php
 $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
 $agendaRows = $agenda->map(function ($b) {
  $extras = (int) $b->addons->sum('amount');
  $paid = (int) $b->payments->where('status', 'aprobado')->sum('amount');
  $total = (int) $b->price_final + $extras;
  return ['b' => $b, 'state' => $b->agendaState(), 'extras' => $extras, 'paid' => $paid, 'total' => $total, 'balance' => max(0, $total - $paid)];
 });
 $groupsDef = [
  'todas' => ['Todas', null],
  'por_llegar' => ['Por llegar', ['por_llegar', 'atrasada']],
  'en_alojamiento' => ['En alojamiento', ['en_alojamiento']],
  'finalizada' => ['Finalizadas', ['finalizada']],
  'pendiente' => ['Pendientes de pago', ['pendiente']],
  'otras' => ['Canceladas / no llegaron', ['cancelada', 'vencida', 'no_show']],
 ];
 $countOf = fn ($keys) => $keys === null ? $agendaRows->count() : $agendaRows->whereIn('state.key', $keys)->count();
@endphp
<section class="calendar-card agenda-card sheet-card">
 <h2>Reservas del día <span class="hist-count">{{ $agendaRows->count() }} {{ Str::plural('reserva', $agendaRows->count()) }} · toca una fila para ver el detalle</span></h2>
 <div class="agenda-chips">
  @foreach ($groupsDef as $gk => [$glabel, $gkeys])
   <button type="button" class="agenda-chip {{ $gk === 'todas' ? 'on' : '' }}" data-filter="{{ $gk }}" data-keys="{{ $gkeys ? implode(',', $gkeys) : '' }}">{{ $glabel }} <b>{{ $countOf($gkeys) }}</b></button>
  @endforeach
 </div>
 <div class="sheet-wrap">
  <table class="sheet" id="agenda-table">
   <thead><tr>
    <th>Entrada</th><th>Salida</th><th>Hab.</th><th>Cliente</th><th>Reserva</th><th>Origen</th><th>Estado</th>
    <th>Check-in</th><th>Salida real</th><th class="n">Total</th><th class="n">Pagado</th><th class="n">Saldo</th><th></th>
   </tr></thead>
   <tbody>
   @forelse ($agendaRows as $r)
    @php $b = $r['b']; $k = $r['state']['key']; @endphp
    <tr data-href="{{ route('reservations.show', $b->code) }}" data-state="{{ $k }}" class="{{ in_array($k, ['cancelada', 'vencida', 'no_show'], true) ? 'dim' : '' }}">
     <td><b>{{ $b->starts_at->format('H:i') }}</b>@if(! $b->starts_at->isSameDay($date)) <small>({{ $b->starts_at->locale('es')->isoFormat('ddd D') }})</small>@endif</td>
     <td>{{ $b->ends_at->format('H:i') }}</td>
     <td><b>{{ $b->room->name }}</b> <small>{{ $b->room->category->name }}</small></td>
     <td>{{ $b->customer->name }}</td>
     <td><a class="code" href="{{ route('reservations.show', $b->code) }}">{{ $b->code }}</a></td>
     <td><span class="org org-{{ $b->origin ?: 'none' }}">{{ $b->originLabel() }}</span></td>
     <td><span class="ag ag-{{ $k }}">{{ $r['state']['label'] }}</span></td>
     <td>{{ $b->checked_in_at ? $b->checked_in_at->timezone('America/Santiago')->format('H:i') : '—' }}</td>
     <td>{{ $b->checked_out_at ? $b->checked_out_at->timezone('America/Santiago')->format('H:i') : '—' }}</td>
     <td class="n">{{ $money($r['total']) }}</td>
     <td class="n">{{ $money($r['paid']) }}</td>
     <td class="n {{ $r['balance'] > 0 && ! in_array($k, ['cancelada', 'vencida', 'no_show'], true) ? 'owe' : '' }}">{{ $r['balance'] > 0 ? $money($r['balance']) : '—' }}</td>
     <td>
      @if (in_array($k, ['por_llegar', 'atrasada'], true))<a class="ag-act" href="{{ route('bookings.checkin.show', $b->code) }}">Check-in →</a>
      @elseif ($k === 'en_alojamiento')<a class="ag-act" href="{{ route('bookings.finalize.show', $b->code) }}">Finalizar →</a>
      @elseif ($k === 'pendiente')<a class="ag-act" href="{{ route('payments.create', ['code' => $b->code]) }}">Cobrar →</a>
      @endif
     </td>
    </tr>
   @empty
    <tr class="zero"><td colspan="13">No hay reservas para este día.</td></tr>
   @endforelse
   <tr class="zero" id="agenda-empty" style="display:none"><td colspan="13">No hay reservas en este grupo.</td></tr>
   </tbody>
  </table>
 </div>
</section>
<style>
.agenda-chips{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 10px}
.agenda-chip{background:#fff;border:1px solid #d1d5db;color:#374151;border-radius:999px;padding:6px 13px;font-size:12.5px;font-weight:600;cursor:pointer}
.agenda-chip b{margin-left:4px;color:#c2410c}
.agenda-chip.on{background:#111;border-color:#111;color:#fff}.agenda-chip.on b{color:#ffb27a}
.ag{display:inline-block;border-radius:999px;padding:3px 10px;font-size:11.5px;font-weight:700;white-space:nowrap}
.ag-por_llegar{background:#ffedd5;color:#c2410c}.ag-atrasada{background:#fee2e2;color:#b91c1c}
.ag-en_alojamiento{background:#dcfce7;color:#15803d}.ag-finalizada{background:#e0e7ff;color:#3730a3}
.ag-pendiente{background:#fef3c7;color:#92400e}.ag-cancelada,.ag-vencida,.ag-no_show{background:#f3f4f6;color:#6b7280}
table.sheet tr.dim td{color:#9ca3af}
a.ag-act{color:#c2410c;font-weight:700;text-decoration:none;white-space:nowrap}
</style>
<script>
document.querySelectorAll('.agenda-chip').forEach(function (chip) {
 chip.addEventListener('click', function () {
  document.querySelectorAll('.agenda-chip').forEach(function (c) { c.classList.toggle('on', c === chip); });
  var keys = chip.dataset.keys ? chip.dataset.keys.split(',') : null, shown = 0;
  document.querySelectorAll('#agenda-table tbody tr[data-state]').forEach(function (tr) {
   var ok = !keys || keys.indexOf(tr.dataset.state) !== -1; tr.style.display = ok ? '' : 'none'; if (ok) shown++;
  });
  var empty = document.getElementById('agenda-empty'); if (empty) empty.style.display = shown ? 'none' : '';
 });
});
</script>
@endif
@if($view!=='history')
<div class="calendar-split calendar-split-one">
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
</div>
@endif
@if($view==='history')
@php
 $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
 $histRows = $pastBookings->sortBy('starts_at')->values()->map(function ($b) {
  $extras = (int) $b->addons->sum('amount');
  $paid = (int) $b->payments->where('status', 'aprobado')->sum('amount');
  return ['b' => $b, 'extras' => $extras, 'paid' => $paid, 'total' => (int) $b->price_final + $extras, 'balance' => max(0, (int) $b->price_final + $extras - $paid)];
 });
 $statusLabel = ['PENDIENTE_PAGO' => 'Pendiente de pago', 'CONFIRMADA' => 'Confirmada', 'CHECK_IN' => 'En curso', 'FINALIZADA' => 'Finalizada'];
@endphp
<section class="calendar-card sheet-card">
 <h2>✓ Historial {{ $period==='month' ? 'del mes' : ($period==='week' ? 'de la semana' : 'del día') }} <span class="hist-count">{{ $histRows->count() }} {{ Str::plural('reserva', $histRows->count()) }} · toca una fila para ver el detalle</span></h2>
 <div class="sheet-wrap">
  <table class="sheet">
   <thead><tr>
    <th class="n">N°</th><th>Fecha</th><th>Entrada</th><th>Salida</th><th class="n">Duración</th><th>Reserva</th><th>Origen</th><th>Cliente</th><th>Teléfono</th>
    <th>Hab.</th><th>Categoría</th><th class="n">Pers.</th><th>Tarifa</th>
    <th class="n">Alojamiento</th><th class="n">Extras</th><th class="n">Total</th><th class="n">Pagado</th><th class="n">Saldo</th><th>Estado</th>
   </tr></thead>
   <tbody>
   @forelse ($histRows as $r)
    @php $b = $r['b']; @endphp
    <tr data-href="{{ route('reservations.show', $b->code) }}">
     <td class="n">{{ $loop->iteration }}</td>
     <td>{{ $b->starts_at->locale('es')->isoFormat('ddd D MMM') }}</td>
     <td>{{ $b->starts_at->format('H:i') }}</td>
     <td>{{ $b->ends_at->format('H:i') }}</td>
     <td class="n">{{ \App\Support\TimeFormat::minutes($b->duration_minutes) }}</td>
     <td><a class="code" href="{{ route('reservations.show', $b->code) }}">{{ $b->code }}</a></td>
     <td><span class="org org-{{ $b->origin ?: 'none' }}">{{ $b->originLabel() }}</span></td>
     <td>{{ $b->customer->name }}</td>
     <td>{{ $b->customer->phone ?: '—' }}</td>
     <td>{{ $b->room->name }}</td>
     <td>{{ $b->room->category->name }}</td>
     <td class="n">{{ $b->guests_count }}</td>
     <td>{{ $b->rate_rule_name_snapshot ?: '—' }}</td>
     <td class="n">{{ $money($b->price_final) }}</td>
     <td class="n">{{ $r['extras'] ? $money($r['extras']) : '—' }}</td>
     <td class="n"><b>{{ $money($r['total']) }}</b></td>
     <td class="n">{{ $money($r['paid']) }}</td>
     <td class="n {{ $r['balance'] > 0 ? 'owe' : '' }}">{{ $r['balance'] > 0 ? $money($r['balance']) : '—' }}</td>
     <td><span class="st st-{{ strtolower($b->booking_status) }}">{{ $statusLabel[$b->booking_status] ?? $b->booking_status }}</span></td>
    </tr>
   @empty
    <tr class="zero"><td colspan="19">Todavía no pasó ninguna reserva en este período.</td></tr>
   @endforelse
   @if ($histRows->isNotEmpty())
    <tr class="total">
     <td colspan="13">Total · {{ $histRows->count() }} {{ Str::plural('reserva', $histRows->count()) }}</td>
     <td class="n">{{ $money($histRows->sum(fn ($r) => $r['b']->price_final)) }}</td>
     <td class="n">{{ $money($histRows->sum('extras')) }}</td>
     <td class="n">{{ $money($histRows->sum('total')) }}</td>
     <td class="n">{{ $money($histRows->sum('paid')) }}</td>
     <td class="n">{{ $money($histRows->sum('balance')) }}</td>
     <td></td>
    </tr>
   @endif
   </tbody>
  </table>
 </div>
</section>
@endif
<script>document.addEventListener('click',function(e){var r=e.target.closest('table.sheet tr[data-href]');if(r&&!e.target.closest('a'))location.href=r.dataset.href;});</script>
</main>
</body></html>
