<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Limpieza del historial generado durante las pruebas (solo soporte). Borra registros
 * de operación; no toca reservas, pagos ni clientes (eso se hace desde cada reserva).
 */
class CleanupController extends Controller
{
    /** Acciones de auditoría que forman el historial de aseo y rondas de mucamas. */
    private const ASEO_ACTIONS = [
        'habitacion.aseo_listo', 'habitacion.enviar_aseo', 'habitacion.aseo_reportado', 'habitacion.qr_visita',
        'habitacion.revision_previa', 'habitacion.ronda_ocupada', 'habitacion.estado_operativo',
    ];

    private const OPTIONS = [
        'aseo' => ['Aseo y rondas de mucamas', 'Aseos reportados y confirmados, visitas por QR, rondas de turno y cambios de estado de habitaciones. Es lo que alimenta Operaciones → Aseo del día y la actividad de mucamas.'],
        'inspecciones' => ['Inspecciones y mantención', 'Las revisiones de habitación y los avisos de mantención pendientes.'],
        'turnos' => ['Entradas y salidas del personal', 'El registro de horas (cada inicio y cierre de sesión), usado para el cálculo de horas.'],
        'auditoria' => ['Todo el registro de auditoría', 'Todas las acciones registradas (reservas, pagos, cambios de ajustes). Quita la trazabilidad de lo hecho hasta hoy; úsalo solo si estás empezando de cero.'],
    ];

    public function index(): View
    {
        return view('admin.cleanup.index', [
            'options' => collect(self::OPTIONS)->map(fn ($o, $key) => ['title' => $o[0], 'help' => $o[1], 'count' => $this->query($key, null)->count()]),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'what' => ['required', 'array', 'min:1'],
            'what.*' => ['in:'.implode(',', array_keys(self::OPTIONS))],
            'before' => ['nullable', 'date'],
            'confirm_text' => ['required'],
        ], ['what.required' => 'Elige al menos un tipo de historial para limpiar.']);

        if (trim((string) $data['confirm_text']) !== 'ELIMINAR') {
            return back()->withInput()->withErrors(['confirm_text' => 'Para limpiar tienes que escribir ELIMINAR en el cuadro de confirmación.']);
        }

        $before = ! empty($data['before']) ? $data['before'].' 00:00:00' : null;
        $deleted = [];
        DB::transaction(function () use ($data, $before, &$deleted) {
            foreach ($data['what'] as $key) {
                $deleted[$key] = $this->query($key, $before)->delete();
                if ($key === 'aseo') {
                    // Lo que las mucamas "dejaron visto" también forma parte del historial de aseo.
                    DB::table('staff')->update(['last_qr_room_id' => null, 'last_qr_seen_at' => null]);
                    DB::table('rooms')->where('operational_status', '!=', 'aseo')->update(['aseo_reported_by' => null, 'aseo_reported_at' => null]);
                }
            }
        });
        // El propio acto de limpiar queda anotado (después, para que no lo borre la opción "auditoría").
        AuditLog::record(auth()->id(), 'limpieza.historial', 'Sistema', 0, null, ['borrado' => $deleted, 'antes_de' => $data['before'] ?? null]);

        $summary = collect($deleted)->map(fn ($n, $key) => self::OPTIONS[$key][0].': '.$n)->implode(' · ');

        return redirect()->route('admin.cleanup.index')->with('status', 'Historial limpiado — '.$summary.'.');
    }

    private function query(string $key, ?string $before)
    {
        $query = match ($key) {
            'aseo' => DB::table('audit_logs')->whereIn('action', self::ASEO_ACTIONS),
            'inspecciones' => DB::table('room_inspections'),
            'turnos' => DB::table('staff_shift_logs'),
            'auditoria' => DB::table('audit_logs'),
        };

        return $before ? $query->where('created_at', '<', $before) : $query;
    }
}
