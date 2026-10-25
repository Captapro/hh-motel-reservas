@extends('admin.layout')
@section('title', 'Personal')
@section('content')
    <div class="page-head">
        <div>
            <h1>Personal</h1>
            <p class="sub">Cuentas para entrar al sistema (izquierda) y personal para turnos y horas (derecha).</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.shifts.index') }}">Panel de turnos →</a>
    </div>

    @if (session('status') && str_contains(session('status'), 'contraseña'))
        <div class="pill" style="display:block; padding:10px 14px; margin-bottom:14px; background:#0f2b22; border:1px solid #1c9169; color:#6ee7b7;">{{ session('status') }}</div>
    @endif

    <div class="cols cols-2">
        {{-- ===== Cuentas de acceso ===== --}}
        <div class="card">
            <div class="cols-h"><h2>Cuentas de acceso <span class="pill">{{ $accounts->count() }}</span></h2></div>
            <p class="sub" style="font-size:12px;"><b>Administrador</b> ve todo; <b>anfitrión</b> no entra a Administración. Las mucamas no necesitan cuenta: van en el personal para turnos.</p>

            <form method="POST" action="{{ route('admin.accounts.store') }}" class="add-row" style="margin-bottom:12px;">
                @csrf
                <div><label>Nombre</label><input name="name" maxlength="100" required placeholder="Ej. Camila"></div>
                <div><label>Email</label><input type="email" name="email" maxlength="255" required placeholder="camila@hhmotel.cl"></div>
                <div style="flex:0 1 150px;"><label>Rol</label>
                    <select name="role" required>
                        <option value="recepcion">Anfitrión</option>
                        <option value="administrador">Administrador</option>
                        @if (auth()->user()?->isSoporte())<option value="soporte">Soporte (master)</option>@endif
                        <option value="marketing">Marketing</option>
                    </select>
                </div>
                <button class="btn" type="submit">+ Crear</button>
            </form>

            <table class="tbl-compact">
                <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td>{{ $account->name }}</td>
                            <td style="color:#aaa;">{{ $account->email }}</td>
                            <td>{{ ['recepcion' => 'Anfitrión', 'soporte' => 'Soporte (master)'][$account->role] ?? ucfirst($account->role) }}</td>
                            <td><span class="pill {{ $account->is_active ? '' : 'pill-bad' }}">{{ $account->is_active ? 'ACTIVA' : 'DESACTIVADA' }}</span></td>
                            <td style="text-align:right;">
                                <details class="pop">
                                    <summary>Editar</summary>
                                    <div class="pop-body">
                                        <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="pop-grid">
                                            @csrf @method('PUT')
                                            <div><label>Nombre</label><input name="name" value="{{ $account->name }}" maxlength="100" required></div>
                                            <div><label>Rol</label>
                                                <select name="role">
                                                    <option value="recepcion" @selected($account->role === 'recepcion')>Anfitrión</option>
                                                    <option value="administrador" @selected($account->role === 'administrador')>Administrador</option>
                                                    @if (auth()->user()?->isSoporte())<option value="soporte" @selected($account->role === 'soporte')>Soporte (master)</option>@endif
                                                    <option value="marketing" @selected($account->role === 'marketing')>Marketing</option>
                                                </select>
                                            </div>
                                            <div class="full"><label>Email</label><input type="email" name="email" value="{{ $account->email }}" maxlength="255" required></div>
                                            <div><label>Estado</label>
                                                <select name="is_active">
                                                    <option value="1" @selected($account->is_active)>Activa</option>
                                                    <option value="0" @selected(! $account->is_active)>Desactivada</option>
                                                </select>
                                            </div>
                                            <div><label>Nueva contraseña</label><input type="password" name="password" placeholder="Opcional" autocomplete="new-password"></div>
                                            <div class="full"><button class="btn" type="submit" style="padding:7px 14px; font-size:13px;">Guardar</button></div>
                                        </form>
                                        <div class="danger">
                                            @if ($account->id !== auth()->id())
                                                <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ e($account->name) }}? No se puede deshacer.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="danger-btn">Eliminar cuenta</button>
                                                </form>
                                            @else
                                                <span style="font-size:12px;color:#888;">Es tu cuenta actual: no se puede eliminar desde aquí.</span>
                                            @endif
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:#666;">Todavía no hay cuentas creadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===== Personal para turnos ===== --}}
        <div class="card">
            <div class="cols-h"><h2>Personal para turnos <span class="pill">{{ $staff->count() }}</span></h2></div>
            <p class="sub" style="font-size:12px;">Anfitriones, mucamas y otros roles, para armar turnos y calcular horas extra. Las mucamas entran al <a class="link" href="{{ route('rooms.qr.mucama_panel') }}" target="_blank" rel="noopener">panel de mucamas</a> con su PIN de 4 dígitos.</p>

            <form method="POST" action="{{ route('admin.staff.store') }}" class="add-row" style="margin-bottom:12px;">
                @csrf
                <div><label>Nombre</label><input name="name" maxlength="100" required placeholder="Ej. Leonardo"></div>
                <div><label>Rol</label>
                    <input name="role" maxlength="50" required placeholder="Anfitrión, Mucama" list="existing-roles">
                    <datalist id="existing-roles">
                        @foreach ($staff->pluck('role')->unique() as $role)<option value="{{ $role }}">@endforeach
                    </datalist>
                </div>
                <div style="flex:0 1 90px;"><label>PIN</label><input type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" name="pin" placeholder="1234"></div>
                <div style="flex:0 1 90px;"><label>Hrs/sem.</label><input type="number" name="legal_hours_per_week" min="0" max="100" placeholder="45"></div>
                <button class="btn" type="submit">+ Agregar</button>
            </form>

            <table class="tbl-compact">
                <thead><tr><th>Nombre</th><th>Rol</th><th>PIN</th><th>Hrs/sem.</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($staff as $person)
                        <tr>
                            <td>{{ $person->name }}</td>
                            <td>{{ $person->role }}</td>
                            <td style="color:{{ $person->pin ? '#6fd39a' : '#777' }};">{{ $person->pin ? '✓' : '—' }}</td>
                            <td>{{ $person->legal_hours_per_week ?? '—' }}</td>
                            <td><span class="pill {{ $person->is_active ? '' : 'pill-bad' }}">{{ $person->is_active ? 'ACTIVO' : 'INACTIVO' }}</span></td>
                            <td style="text-align:right;">
                                <details class="pop">
                                    <summary>Editar</summary>
                                    <div class="pop-body">
                                        <form method="POST" action="{{ route('admin.staff.update', $person) }}" class="pop-grid">
                                            @csrf @method('PUT')
                                            <div><label>Nombre</label><input name="name" value="{{ $person->name }}" maxlength="100" required></div>
                                            <div><label>Rol</label><input name="role" value="{{ $person->role }}" maxlength="50" required></div>
                                            <div><label>PIN</label><input type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" name="pin" placeholder="{{ $person->pin ? 'Vacío = no cambiar' : '4 dígitos' }}"></div>
                                            <div><label>Horas legales/semana</label><input type="number" name="legal_hours_per_week" value="{{ $person->legal_hours_per_week }}" min="0" max="100"></div>
                                            <div><label>Estado</label>
                                                <select name="is_active">
                                                    <option value="1" @selected($person->is_active)>Activo</option>
                                                    <option value="0" @selected(! $person->is_active)>Inactivo</option>
                                                </select>
                                            </div>
                                            <div style="align-self:end;"><button class="btn" type="submit" style="padding:7px 14px; font-size:13px;">Guardar</button></div>
                                        </form>
                                        <div class="danger">
                                            <form method="POST" action="{{ route('admin.staff.destroy', $person) }}" onsubmit="return confirm('¿Eliminar a {{ e($person->name) }} del personal? También se borran sus turnos y su registro de horas. No se puede deshacer.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="danger-btn">Eliminar del personal</button>
                                            </form>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:#666;">Todavía no hay nadie cargado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
