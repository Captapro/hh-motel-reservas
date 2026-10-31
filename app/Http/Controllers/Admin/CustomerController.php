<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerSegmentRule;
use App\Services\Historical\HistoricalCustomerAnalysis;
use App\Services\Integrations\GhlClient;
use App\Services\Integrations\GhlMarketingConsent;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $segmentFilter = (string) $request->query('segment', '');

        $customers = Customer::query()
            ->when($query !== '', function ($q) use ($query) {
                $digits = preg_replace('/\D/', '', $query);
                $q->where(function ($c) use ($query, $digits) {
                    $c->where('name', 'ilike', "%{$query}%");
                    if ($digits !== '') {
                        $c->orWhere('phone_e164', 'ilike', "%{$digits}%");
                    }
                });
            })
            ->withCount(['bookings as bookings_count' => function ($q) {
                $q->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA']);
            }])
            ->orderByDesc('bookings_count')
            ->get()
            ->map(function (Customer $customer) {
                $customer->computed_segment = $customer->segment();

                return $customer;
            });

        if ($segmentFilter !== '') {
            $customers = $customers->filter(fn (Customer $c) => $c->computed_segment['type'] === $segmentFilter)->values();
        }

        // Cruce con las planillas históricas por RUT (solo administrador): solo aplica a clientes con RUT cargado.
        $historical = $request->user()->isAdministrador()
            ? app(HistoricalCustomerAnalysis::class)->forDocuments($customers->pluck('rut'))
            : collect();

        return view('admin.customers.index', ['historical' => $historical, 'customers' => $customers, 'query' => $query, 'segmentFilter' => $segmentFilter, 'segmentRules' => CustomerSegmentRule::orderBy('display_order')->get()]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        foreach ($request->input('rules', []) as $id => $rule) {
            $model = CustomerSegmentRule::find($id);
            if ($model) {
                $model->update(['label' => $rule['label'] ?? $model->label, 'minimum_stays' => (int) ($rule['minimum_stays'] ?? $model->minimum_stays), 'analysis_window_days' => ($rule['analysis_window_days'] ?? '') !== '' ? (int) $rule['analysis_window_days'] : null, 'stars' => min(5, max(0, (int) ($rule['stars'] ?? $model->stars))), 'stale_after_days' => ($rule['stale_after_days'] ?? '') !== '' ? (int) $rule['stale_after_days'] : null]);
            }
        }

        return back()->with('status', 'Reglas de clasificación actualizadas.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $bookings = $customer->bookings()->with(['payments', 'addons'])->get();
        $withBookings = $request->boolean('with_bookings');
        $force = $request->boolean('force');

        if ($force) {
            // Para limpiar datos de prueba: borra también pagos y consumos,
            // pero solo si ninguna reserva llegó a ocurrir, y pide escribir
            // la palabra ELIMINAR.
            if (! $customer->canForceDelete()) {
                return back()->withErrors(['customer' => 'No se puede eliminar: tiene reservas que sí ocurrieron (check-in, finalizadas o vigentes). Eso es historial real y no se borra.']);
            }
            if (trim((string) $request->input('confirm_text')) !== 'ELIMINAR') {
                return back()->withErrors(['customer' => 'Para eliminar el cliente y todo su historial tienes que escribir ELIMINAR en el cuadro de confirmación.']);
            }
        } elseif ($bookings->isNotEmpty()) {
            if (! $withBookings) {
                return back()->withErrors(['customer' => 'Este cliente tiene '.$bookings->count().' reserva(s) en su historial. Para eliminarlo hay que confirmar que también se borran sus reservas.']);
            }
            // Pagos, consumos, cupones o estadías reales son registros de
            // dinero o de lo que efectivamente ocurrió: sin "force" no se borran.
            $blockers = $customer->historyBlockers();
            if ($blockers) {
                return back()->withErrors(['customer' => 'No se puede eliminar: tiene '.implode(', ', $blockers).'. Es historial de caja y no se borra.']);
            }
        }

        $old = $customer->only(['id', 'name', 'phone_e164', 'email']) + [
            'forzado' => $force,
            'reservas' => $bookings->map(fn ($b) => [
                'code' => $b->code, 'estado' => $b->booking_status, 'precio' => $b->price_final,
                'pagos' => $b->payments->map(fn ($p) => ['monto' => $p->amount, 'estado' => $p->status, 'voucher' => $p->voucher_number, 'boleta' => $p->receipt_number])->all(),
                'consumos' => $b->addons->map(fn ($a) => ['detalle' => $a->description, 'monto' => $a->amount])->all(),
            ])->all(),
        ];

        try {
            DB::transaction(function () use ($customer, $bookings, $force) {
                if ($force) {
                    DB::table('payments')->whereIn('booking_id', $bookings->pluck('id'))->delete();
                }
                if ($bookings->isNotEmpty()) {
                    $customer->bookings()->delete();
                }
                $customer->delete();
            });
        } catch (QueryException $e) {
            return back()->withErrors(['customer' => 'No se pudo eliminar: el cliente tiene registros asociados. Bloquéalo en "Lista negra" si no quieres que vuelva a reservar.']);
        }
        AuditLog::record(auth()->id(), 'cliente.eliminar', 'Customer', $old['id'], $old, null);

        return redirect()->route('admin.customers.index')->with('status', 'Cliente '.$old['name'].' eliminado'.($bookings->isNotEmpty() ? ' junto con '.$bookings->count().' reserva(s)' : '').'. (No se borra de GHL: si hace falta, elimínalo allá.)');
    }

    public function blacklist(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['blacklist_status' => ['required', 'in:none,warning,blocked'], 'blacklist_reason' => ['nullable', 'string', 'max:1000']]);
        $customer->update($data + ['blacklisted_at' => $data['blacklist_status'] === 'none' ? null : now()]);

        return back()->with('status', $data['blacklist_status'] === 'none' ? 'Cliente retirado de la lista negra.' : 'Estado de lista negra actualizado.');
    }

    public function importBlacklist(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $phone = trim((string) ($row[0] ?? ''));
            if (! $phone || ! preg_match('/\d{7,}/', preg_replace('/\D/', '', $phone))) {
                continue;
            }
            $customer = Customer::where('phone_e164', 'like', '%'.preg_replace('/\D/', '', $phone).'%')->first();
            if ($customer) {
                $customer->update(['blacklist_status' => 'blocked', 'blacklist_reason' => $row[1] ?? 'Importado desde lista negra', 'blacklisted_at' => now()]);
                $count++;
            }
        }
        fclose($handle);

        return back()->with('status', "Lista negra procesada: {$count} clientes encontrados.");
    }

    public function show(Request $request, Customer $customer): View
    {
        $now = now();

        $upcoming = $customer->bookings()
            ->with('room.category')
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->where('ends_at', '>=', $now)
            ->orderBy('starts_at')
            ->get();

        $past = $customer->bookings()
            ->with('room.category')
            ->where('ends_at', '<', $now)
            ->orderByDesc('starts_at')
            ->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'segment' => $customer->segment(),
            'monthlyVisits' => $customer->monthlyVisits(),
            'historical' => $request->user()->isAdministrador() && filled($customer->rut)
                ? app(HistoricalCustomerAnalysis::class)->forDocuments([$customer->rut])->first()
                : null,
            'upcoming' => $upcoming,
            'past' => $past,
            'now' => $now,
        ]);
    }

    /**
     * El teléfono no se edita acá -- es la llave con la que identificamos al
     * cliente (y con la que GHL empareja el contacto); cambiarlo sería crear
     * una identidad distinta, no corregir un dato. Al guardar, sincroniza el
     * contacto en GHL al toque (no solo al crear una reserva nueva) para que
     * un correo agregado ahora mismo ya quede reflejado si el cliente
     * escribe por WhatsApp antes de su próxima estadía.
     */
    public function update(Request $request, Customer $customer, GhlClient $ghl): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'document_type' => ['nullable', 'in:rut,pasaporte'],
            'rut' => ['nullable', 'string', 'max:20'],
            'passport_number' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $optIn = (bool) ($validated['marketing_opt_in'] ?? false);
        unset($validated['marketing_opt_in']);
        $customer->update($validated);
        if ($customer->marketing_opt_in !== $optIn) {
            $customer->update(['marketing_opt_in' => $optIn, 'marketing_opt_in_at' => now()]);
            AuditLog::record(auth()->id(), $optIn ? 'cliente.marketing_si' : 'cliente.marketing_no', 'Customer', $customer->id, null, ['via' => 'ficha']);
        }

        $status = 'Datos del cliente actualizados.';
        try {
            if ($ghl->isConfigured()) {
                $contactId = $ghl->upsertContact($customer->phone_e164, $customer->name, $customer->email);
                $customer->update(['ghl_contact_id' => $customer->ghl_contact_id ?: $contactId, 'ghl_synced_at' => now()]);
                (new GhlMarketingConsent($ghl))->apply($customer, $contactId);
                $status .= ' Sincronizado con GHL.';
            } else {
                $status .= ' (GHL no está configurado -- no se sincronizó.)';
            }
        } catch (Throwable $e) {
            Log::warning('GHL sync falló al editar el cliente '.$customer->id, ['error' => $e->getMessage()]);
            $status .= ' No se pudo sincronizar con GHL (se reintentará en su próxima reserva).';
        }

        return redirect()->route('admin.customers.show', $customer)->with('status', $status);
    }
}
