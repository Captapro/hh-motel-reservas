{{-- Subcategorías de Analytics. «Clientes que vuelven» muestra nombres y RUT: solo soporte. --}}
@once
    <style>
        .an-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0 0 14px; }
        .an-nav .an-parent { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#c2410c; padding-right:4px; }
        .an-nav .an-sep { color:#9ca3af; font-size:15px; margin:0 2px 0 -2px; }
        .an-nav a { padding:7px 14px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; color:#374151; font-size:13px; text-decoration:none; }
        .an-nav a:hover { border-color:#ff7918; color:#c2410c; }
        .an-nav a.on { background:#ff7918; border-color:#ff7918; color:#fff; font-weight:600; }
    </style>
@endonce
<div class="an-nav">
    <span class="an-parent">Analytics</span><span class="an-sep">›</span>
    <a href="{{ route('admin.analytics.index') }}" @class(['on' => $on === 'analytics'])>Resumen</a>
    <a href="{{ route('admin.analytics.customers') }}" @class(['on' => $on === 'customers'])>Comportamiento de clientes</a>
    <a href="{{ route('admin.analytics.extras') }}" @class(['on' => $on === 'extras'])>Extras por mes</a>
    <a href="{{ route('admin.analytics.records') }}" @class(['on' => $on === 'records'])>Récords de venta</a>
    @if (auth()->user()?->isSoporte())
        <a href="{{ route('admin.analytics.returning') }}" @class(['on' => $on === 'returning'])>Clientes que vuelven</a>
    @endif
</div>
