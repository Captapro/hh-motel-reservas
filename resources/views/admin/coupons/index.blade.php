@extends('admin.layout')
@section('title', 'Cupones')
@section('content')
    <div class="actions" style="justify-content:space-between; margin-bottom:18px;">
        <div><h1>Cupones y promociones</h1><p class="sub">No acumulables entre sí por defecto.</p></div>
        <a class="btn" href="{{ route('admin.coupons.create') }}">+ Nuevo cupón</a>
    </div>
    <div class="card">
        <table>
            <thead><tr><th>Código</th><th>Descuento</th><th>Verificación</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @foreach ($coupons as $coupon)
                    <tr>
                        <td>{{ $coupon->code }}<div style="color:#888; font-size:12px;">{{ $coupon->internal_name }}</div></td>
                        <td>{{ $coupon->discount_type === 'percentage' ? $coupon->discount_value.'%' : '$'.number_format($coupon->discount_value, 0, ',', '.') }}</td>
                        <td>{{ $coupon->requires_verification ? $coupon->verification_note : '—' }}</td>
                        <td><span class="pill">{{ $coupon->is_active ? 'ACTIVO' : 'INACTIVO' }}</span>@unless ($coupon->is_public) <span class="pill" title="No aparece en el catálogo">INTERNO</span>@endunless</td>
                        <td style="white-space:nowrap;">
                            <a class="link" href="{{ route('admin.coupons.edit', $coupon) }}">Editar</a>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" style="display:inline; margin-left:12px;" onsubmit="return confirm('¿Eliminar el cupón {{ $coupon->code }}? No se puede deshacer.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="link" style="background:none; border:0; padding:0; color:#d0384f; cursor:pointer; font:inherit; width:auto; margin:0;">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
