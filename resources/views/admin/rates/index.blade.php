@extends('admin.layout')
@section('title', 'Tarifas')
@section('content')
    <style>
        .rate-price-grid { width:100%; border-collapse:collapse; margin-top:14px; font-size:13.5px; }
        .rate-price-grid th, .rate-price-grid td { padding:8px 12px; text-align:center; border:1px solid #333; }
        .rate-price-grid th { color:#999; font-weight:500; font-size:11px; text-transform:uppercase; }
        .rate-price-grid td:first-child, .rate-price-grid th:first-child { text-align:left; }
        .rate-price-grid .missing { color:#555; }
    </style>
    <h1>Tarifas</h1>
    <p class="sub">HH y HOT — los horarios en que aplica cada una se definen por código; los precios se editan aquí.</p>
    @foreach ($rateRules as $rule)
        <div class="card" style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:10px;">
                <div>
                    <strong>{{ $rule->name }}</strong> — {{ $rule->description }}
                    <span class="pill" style="margin-left:8px;">{{ $rule->is_active ? 'ACTIVA' : 'INACTIVA' }}</span>
                </div>
                <div>
                    Adicional por persona: <strong>${{ number_format($rule->extra_person_price, 0, ',', '.') }}</strong>
                    · Hora adicional: <strong>${{ number_format((int) $rule->extra_hour_price, 0, ',', '.') }}</strong>
                    · <a class="link" href="{{ route('admin.rates.edit', $rule) }}">Editar precios</a>
                </div>
            </div>

            @php $durations = $priceGrids[$rule->id]['durations']; $grid = $priceGrids[$rule->id]['grid']; @endphp
            @if ($durations->isEmpty())
                <p class="sub" style="margin-top:12px;">Todavía no tiene precios cargados.</p>
            @else
                <table class="rate-price-grid">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            @foreach ($durations as $duration)
                                <th>{{ $duration / 60 }} h</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            @continue(! isset($grid[$category->id]))
                            <tr>
                                <td>{{ $category->name }}</td>
                                @foreach ($durations as $duration)
                                    <td>
                                        @if (isset($grid[$category->id][$duration]))
                                            ${{ number_format($grid[$category->id][$duration]->price, 0, ',', '.') }}
                                        @else
                                            <span class="missing">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@endsection
