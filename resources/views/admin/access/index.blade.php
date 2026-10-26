@extends('admin.layout')
@section('title', 'Perfiles y accesos')
@section('content')
    <h1>Perfiles y accesos</h1>
    <p class="sub">Qué secciones ve cada perfil. Marca las que puede usar; el menú y las pantallas se ajustan solos.</p>

    <form method="POST" action="{{ route('admin.access.update') }}">
        @csrf @method('PUT')
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Sección</th>
                        <th style="text-align:center;">Master</th>
                        <th style="text-align:center;">Administrador</th>
                        @foreach ($roles as $label)
                            <th style="text-align:center;">{{ $label }}</th>
                        @endforeach
                        <th style="text-align:center;">Mucama</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $key => [$name])
                        <tr>
                            <td>{{ $name }}</td>
                            <td style="text-align:center; color:#6fd39a; white-space:nowrap;">✓ siempre</td>
                            <td style="text-align:center; color:#6fd39a; white-space:nowrap;">✓ siempre</td>
                            @foreach ($roles as $role => $label)
                                <td style="text-align:center;">
                                    <input type="checkbox" name="access[{{ $role }}][{{ $key }}]" value="1" @checked($matrix[$role][$key]) style="width:auto; transform:scale(1.25);" aria-label="{{ $label }}: {{ $name }}">
                                </td>
                            @endforeach
                            <td style="text-align:center; color:#888; font-size:12.5px;">{{ $key === 'operaciones' ? 'Solo su panel (con PIN)' : '—' }}</td>
                        </tr>
                    @endforeach
                    {{-- Secciones que no se pueden repartir: son solo del Master (soporte). --}}
                    @foreach ([
                        'Limpieza (borrar datos de prueba y ventas)',
                        'Perfiles y accesos (esta pantalla)',
                        'Integración GoHighLevel',
                        'Eliminar reservas',
                    ] as $masterOnly)
                        <tr>
                            <td>{{ $masterOnly }} <span style="font-size:11px; color:#888;">· solo Master</span></td>
                            <td style="text-align:center; color:#6fd39a; white-space:nowrap;">✓ siempre</td>
                            <td style="text-align:center; color:#888;">—</td>
                            @foreach ($roles as $label)
                                <td style="text-align:center; color:#888;">—</td>
                            @endforeach
                            <td style="text-align:center; color:#888;">—</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="actions" style="margin-top:14px;"><button class="btn" type="submit">Guardar accesos</button></div>
    </form>

    <div class="card" style="margin-top:22px;">
        <strong>Cosas que no se configuran aquí</strong>
        <ul style="margin:10px 0 0; padding-left:18px; color:#bbb; font-size:13.5px; line-height:1.7;">
            <li><b>Administración</b> (tarifas, ofertas, personal, cuentas) es solo del administrador y del Master. <b>Limpieza, Perfiles y accesos, Integración GHL y Eliminar reservas</b> son solo del Master.</li>
            <li>Crear, ver y cobrar <b>reservas</b> lo puede hacer cualquier perfil con una cuenta; este cuadro controla las <b>secciones del menú</b>.</li>
            <li>Las <b>mucamas</b> no tienen cuenta: entran con su PIN solo a su panel (reportar aseo y ronda); no se configuran aquí.</li>
            <li>Si desmarcas todo para un perfil, esa persona no podrá entrar a ninguna sección.</li>
        </ul>
    </div>
@endsection
