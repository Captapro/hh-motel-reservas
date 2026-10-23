<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AccessSections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function index(): View
    {
        $matrix = [];
        foreach (array_keys(AccessSections::ROLES) as $role) {
            foreach (array_keys(AccessSections::SECTIONS) as $section) {
                $matrix[$role][$section] = AccessSections::allows($role, $section);
            }
        }

        return view('admin.access.index', ['matrix' => $matrix, 'roles' => AccessSections::ROLES, 'sections' => AccessSections::SECTIONS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $old = [];
        $new = [];
        DB::transaction(function () use ($request, &$old, &$new) {
            foreach (array_keys(AccessSections::ROLES) as $role) {
                foreach (array_keys(AccessSections::SECTIONS) as $section) {
                    $allowed = (bool) data_get($request->input('access'), "$role.$section");
                    $old[$role][$section] = AccessSections::allows($role, $section);
                    $new[$role][$section] = $allowed;
                    DB::table('access_permissions')->updateOrInsert(
                        ['role' => $role, 'section' => $section],
                        ['allowed' => $allowed, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            }
        });
        AccessSections::forget();
        AuditLog::record(auth()->id(), 'accesos.editar', 'AccessPermission', 0, $old, $new);

        return redirect()->route('admin.access.index')->with('status', 'Accesos actualizados. Aplican desde el siguiente click de cada persona.');
    }
}
