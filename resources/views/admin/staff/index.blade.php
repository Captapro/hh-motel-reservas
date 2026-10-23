@extends('admin.layout')
@section('title', 'Personal')
@section('content')
    <h1>Personal</h1>

    <div class="card">
        <h2>Cuentas de acceso al sistema</h2>
        <p class="sub">Quién puede entrar a este panel (tablero, reservas, caja) con su propio email y contraseña. <b>Administrador</b> ve todo, <b>anfitrión</b> no entra a Administración (categorías, tarifas, ofertas, personal, etc). Las mucamas no necesitan cuenta acá -- van abajo, en el listado de Personal para turnos.</p>
        @if (session('status') && str_contains(session('status'), 'contraseña'))
            <div class="pill" style="display:block; padding:10px 14px; margin-bottom:14px; background:#0f2b22; border:1px solid #1c9169; color:#6ee7b7;">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('admin.accounts.store') }}">
            @csrf
            <div class="row2">
                <div>
                    <label>Nombre</label>
                    <input name="name" maxlength="100" required placeholder="Ej. Camila">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" maxlength="255" required placeholder="camila@hhmotel.cl">
                </div>
                <div>
                    <label>Rol</label>
                    <select name="role" required>
                        <option value="recepcion">Anfitrión</option>
                        <option value="administrador">Administrador</option>
                        <option value="marketing">Marketing</option>
                    </select>
                </div>
            </div>
            <div class="actions" style="margin-top:16px;"><button class="btn" type="submit">Crear cuenta</button></div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->email }}</td>
                        <td>{{ $account->role === 'recepcion' ? 'Anfitrión' : ucfirst($account->role) }}</td>
                        <td><span class="pill">{{ $account->is_active ? 'ACTIVA' : 'DESACTIVADA' }}</span></td>
                        <td>
                            <details>
                                <summary>Editar</summary>
                                <form method="POST" action="{{ route('admin.accounts.update', $account) }}">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $account->name }}" maxlength="100" required>
                                    <input type="email" name="email" value="{{ $account->email }}" maxlength="255" required>
                                    <select name="role">
                                        <option value="recepcion" @selected($account->role === 'recepcion')>Anfitrión</option>
                                        <option value="administrador" @selected($account->role === 'administrador')>Administrador</option>
                                        <option value="marketing" @selected($account->role === 'marketing')>Marketing</option>
                                    </select>
                                    <select name="is_active">
                                        <option value="1" @selected($account->is_active)>Activa</option>
                                        <option value="0" @selected(!$account->is_active)>Desactivada</option>
                                    </select>
                                    <input type="password" name="password" placeholder="Nueva contraseña (opcional)" autocomplete="new-password">
                                    <button class="btn secondary" type="submit" style="padding:6px 12px; font-size:12.5px;">Guardar</button>
                                </form>
                                @if ($account->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ e($account->name) }}? No se puede deshacer.');" style="margin-top:8px;">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="background:#b3202f;color:#fff;border:0;border-radius:7px;padding:6px 12px;font-size:12.5px;cursor:pointer;">Eliminar cuenta</button>
                                    </form>
                                @else
                                    <p style="margin:8px 0 0;font-size:12px;color:#888;">Es tu cuenta actual: no se puede eliminar desde aquí.</p>
                                @endif
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:#666;">Todavía no hay cuentas creadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h1 style="margin-top:36px;">Personal para turnos</h1>
    <p class="sub">Anfitriones, mucamas y cualquier otro rol -- se usa para armar los turnos y calcular horas extra. No es una cuenta de acceso al sistema. Las mucamas sí tienen un PIN de 4 dígitos para entrar al <a href="{{ route('rooms.qr.mucama_panel') }}" target="_blank" rel="noopener">panel/QR de mucamas</a> como ellas mismas.</p>

    <div class="card">
        <h2>Agregar persona</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}">
            @csrf
            <div class="row2">
                <div>
                    <label>Nombre</label>
                    <input name="name" maxlength="100" required placeholder="Ej. Leonardo">
                </div>
                <div>
                    <label>Rol</label>
                    <input name="role" maxlength="50" required placeholder="Ej. Anfitrión, Mucama" list="existing-roles">
                    <datalist id="existing-roles">
                        @foreach ($staff->pluck('role')->unique() as $role)
                            <option value="{{ $role }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label>PIN (4 dígitos, mucamas)</label>
                    <input type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" name="pin" placeholder="Ej. 1234">
                </div>
                <div>
                    <label>Horas legales por semana</label>
                    <input type="number" name="legal_hours_per_week" min="0" max="100" placeholder="Ej. 45">
                </div>
            </div>
            <div class="actions" style="margin-top:16px;"><button class="btn" type="submit">Agregar</button></div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Nombre</th><th>Rol</th><th>PIN</th><th>Horas legales/semana</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($staff as $person)
                    <tr>
                        <td>{{ $person->name }}</td>
                        <td>{{ $person->role }}</td>
                        <td>{{ $person->pin ? '✓ Asignado' : '— Sin PIN' }}</td>
                        <td>{{ $person->legal_hours_per_week ?? '—' }}</td>
                        <td><span class="pill">{{ $person->is_active ? 'ACTIVO' : 'INACTIVO' }}</span></td>
                        <td>
                            <details>
                                <summary>Editar</summary>
                                <form method="POST" action="{{ route('admin.staff.update', $person) }}">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $person->name }}" maxlength="100" required>
                                    <input name="role" value="{{ $person->role }}" maxlength="50" required>
                                    <input type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" name="pin" placeholder="{{ $person->pin ? 'Nuevo PIN (dejar vacío = no cambiar)' : 'PIN (4 dígitos)' }}">
                                    <input type="number" name="legal_hours_per_week" value="{{ $person->legal_hours_per_week }}" min="0" max="100" placeholder="Horas legales/semana">
                                    <select name="is_active">
                                        <option value="1" @selected($person->is_active)>Activo</option>
                                        <option value="0" @selected(!$person->is_active)>Inactivo</option>
                                    </select>
                                    <button class="btn secondary" type="submit" style="padding:6px 12px; font-size:12.5px;">Guardar</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.destroy', $person) }}" onsubmit="return confirm('¿Eliminar a {{ e($person->name) }} del personal? También se borran sus turnos y su registro de horas. No se puede deshacer.');" style="margin-top:8px;">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:#b3202f;color:#fff;border:0;border-radius:7px;padding:6px 12px;font-size:12.5px;cursor:pointer;">Eliminar del personal</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:#666;">Todavía no hay nadie cargado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a class="link" href="{{ route('admin.shifts.index') }}">→ Ir al panel de turnos</a>
@endsection
