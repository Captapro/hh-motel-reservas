<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SalesAnalystController;
use App\Models\AuditLog;
use App\Models\HelpQuestion;
use App\Models\HelpSetting;
use App\Services\Analyst\ClaudeTransport;
use App\Services\Help\HelpAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asistente de ayuda: el botón "Ayuda" de todas las pantallas del equipo y,
 * para el administrador, las reglas de la casa y el registro de preguntas.
 */
class HelpController extends Controller
{
    public function ask(Request $request, HelpAssistant $assistant, ClaudeTransport $claude): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'page' => ['nullable', 'string', 'max:200'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.q' => ['required', 'string', 'max:1000'],
            'history.*.a' => ['required', 'string', 'max:8000'],
        ], [], ['question' => 'pregunta']);

        abort_unless($claude->configured(), 422, 'El asistente todavía no está configurado. Avisa al administrador.');

        $record = $assistant->answer($request->user(), trim($validated['question']), $validated['page'] ?? null, $validated['history'] ?? []);

        return response()->json([
            'status' => $record->status,
            'answer' => $record->answer,
            'answer_html' => SalesAnalystController::markdown((string) $record->answer),
        ]);
    }

    public function index(): View
    {
        $questions = HelpQuestion::with('user')->latest('id')->paginate(40);

        return view('admin.help.index', [
            'setting' => HelpSetting::current()->load('editor'),
            'questions' => $questions,
            'total' => HelpQuestion::count(),
            'lastWeek' => HelpQuestion::where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $validated = $request->validate(['house_rules' => ['nullable', 'string', 'max:8000']], [], ['house_rules' => 'reglas de la casa']);
        $setting = HelpSetting::current();
        $old = ['house_rules' => $setting->house_rules];
        $setting->update(['house_rules' => trim((string) $validated['house_rules']), 'updated_by' => $request->user()->id]);
        AuditLog::record($request->user()->id, 'ayuda.reglas', 'HelpSetting', $setting->id, $old, ['house_rules' => $setting->house_rules]);

        return back()->with('status', 'Reglas de la casa guardadas. El asistente las usa desde la próxima pregunta.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $deleted = HelpQuestion::query()->delete();
        AuditLog::record($request->user()->id, 'ayuda.borrar_historial', 'HelpQuestion', 0, ['preguntas' => $deleted], null);

        return back()->with('status', 'Se eliminó el historial de preguntas.');
    }
}
