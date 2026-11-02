{{-- Nombre de una persona del historial: enlazado a su ficha si es cliente del sistema. Los nombres que solo vienen de la planilla los ve el administrador. --}}
@if ($r['customer'])
    <a class="link" href="{{ route('admin.customers.show', $r['customer']) }}">{{ $r['customer']->name }}</a>
@elseif (auth()->user()?->isAdministrador())
    {{ $r['name'] }} <span style="font-size:10.5px;color:#9ca3af;">(planilla)</span>
@else
    <span style="color:#6b7280;">Cliente de la planilla</span>
@endif
