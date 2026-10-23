@php
    $navigation = [
        ['rooms.board', 'rooms.*', 'Tablero', 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z'],
        ['calendar.index', 'calendar.*,reservations.*,bookings.*', 'Calendario', 'M3 4h18v17H3z M8 2v4 M16 2v4 M3 9h18 M7 13h3 M14 13h3 M7 17h3'],
        ['admin.customers.index', 'admin.customers.*', 'Clientes', 'M16 7a4 4 0 1 1-8 0a4 4 0 0 1 8 0 M4 21v-3a8 8 0 0 1 16 0v3'],
        ['admin.products.index', 'admin.products.*,admin.combos.*', 'Productos', 'M4 7h16v14H4z M8 7V5a4 4 0 0 1 8 0v2'],
        ['cash.show', 'cash.*,payments.*', 'Caja', 'M3 5h18v16H3z M3 9h18 M15 13h6v4h-6z'],
        ['aseo.daily', 'aseo.*,rooms.inspections.*,shift_round.*,mucamas.activity*', 'Operaciones', 'M8 10l8-8 M6 11l7 7 M3 16l6-6 8 8-6 4z'],
        // Turnos apagado por ahora a pedido explícito -- no va en el tablero
        // general todavía. La ruta sigue viva, solo se saca el ícono del riel.
        ['sales.monthly', 'sales.*', 'Ventas', 'M12 2v20 M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        ['admin.analytics.index', 'admin.analytics.*', 'Analytics', 'M4 21V11h4v10 M10 21V6h4v15 M16 21V3h4v18'],
        ['admin.reviews.index', 'admin.reviews.*', 'Opiniones', 'M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z'],
    ];
    // Cada ícono pertenece a una sección; solo se muestran las que el perfil puede ver.
    $sectionOf = ['Tablero' => 'tablero', 'Calendario' => 'calendario', 'Clientes' => 'clientes', 'Productos' => 'productos', 'Caja' => 'caja',
        'Operaciones' => 'operaciones', 'Ventas' => 'ventas', 'Analytics' => 'analytics', 'Opiniones' => 'opiniones'];
    $navigation = array_values(array_filter($navigation, fn ($item) => ! isset($sectionOf[$item[2]]) || auth()->user()?->canAccess($sectionOf[$item[2]])));
    if (auth()->user()?->isAdministrador()) {
        $navigation[] = ['admin.categories.index', 'admin.categories.*,admin.rooms.*,admin.furniture.*,admin.rates.*,admin.offers.*,admin.upsells.*,admin.coupons.*,admin.staff.*,admin.shifts.*', 'Administración', 'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M6 6l2 2 M16 16l2 2 M6 18l2-2 M16 8l2-2 M16 12a4 4 0 1 1-8 0a4 4 0 0 1 8 0'];
    }
@endphp
<aside class="hh-rail" aria-label="Navegación principal">
    <a class="hh-rail-brand" href="{{ route(auth()->user()?->homeRoute() ?? 'rooms.board') }}" aria-label="HH Motel — Tablero"><b>HH</b><span>MOTEL<small>Recepción</small></span></a>
    <nav>
        @foreach ($navigation as [$destination, $patterns, $label, $path])
            @php $active = request()->routeIs(...explode(',', $patterns)); @endphp
            <a href="{{ route($destination) }}" class="hh-rail-link {{ $active ? 'is-active' : '' }}" title="{{ $label }}" @if($active) aria-current="page" @endif>
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    <div class="hh-rail-footer">HH · Recepción</div>
</aside>
