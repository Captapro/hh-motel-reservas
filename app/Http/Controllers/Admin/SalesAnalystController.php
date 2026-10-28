<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalystNote;
use App\Models\AnalystRun;
use App\Models\AnalystSetting;
use App\Models\AuditLog;
use App\Services\Analyst\AnalystLauncher;
use App\Services\Analyst\ClaudeTransport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Analista de ventas (IA): solo las personas autorizadas en Personal. Cada
 * pregunta se guarda como AnalystRun y se responde en segundo plano; la
 * página consulta el estado hasta que está lista.
 */
class SalesAnalystController extends Controller
{
    public function index(Request $request, ClaudeTransport $claude): View
    {
        $thread = (string) $request->query('conversacion', '');
        if (! Str::isUuid($thread)) {
            $thread = (string) AnalystRun::where('user_id', $request->user()->id)->latest('id')->value('thread');
        }

        $runs = $thread ? $this->visible($request)->where('thread', $thread)->orderBy('id')->get() : collect();

        $threads = $this->visible($request)
            ->selectRaw('thread, MIN(id) as first_id, MAX(created_at) as last_at, COUNT(*) as total')
            ->groupBy('thread')->orderByDesc('last_at')->take(15)->get()
            ->map(fn ($t) => [
                'thread' => $t->thread,
                'title' => Str::limit((string) AnalystRun::whereKey($t->first_id)->value('question'), 60),
                'last_at' => Carbon::parse($t->last_at)->timezone('America/Santiago'),
                'total' => (int) $t->total,
            ]);

        return view('admin.analyst.index', [
            'configured' => $claude->configured(),
            'thread' => $thread ?: (string) Str::uuid(),
            'runs' => $runs,
            'threads' => $threads,
            'setting' => AnalystSetting::current()->load('editor'),
            'notes' => $request->user()->isAdministrador() ? AnalystNote::orderBy('id')->get() : collect(),
        ]);
    }

    public function ask(Request $request, AnalystLauncher $launcher, ClaudeTransport $claude): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'thread' => ['required', 'uuid'],
        ], [], ['question' => 'pregunta']);

        abort_unless($claude->configured(), 422, 'Falta configurar la clave de la API de Claude.');
        // Nadie sigue la conversación de otra persona (salvo el administrador).
        abort_if(AnalystRun::where('thread', $validated['thread'])->exists()
            && ! $this->visible($request)->where('thread', $validated['thread'])->exists(), 403);

        // Una pregunta a la vez por conversación.
        $busy = AnalystRun::where('thread', $validated['thread'])->whereIn('status', ['pendiente', 'trabajando'])
            ->where('created_at', '>', now()->subMinutes(20))->exists();
        abort_if($busy, 409, 'Espera a que termine la respuesta anterior.');

        $run = AnalystRun::create([
            'user_id' => $request->user()->id,
            'thread' => $validated['thread'],
            'question' => trim($validated['question']),
        ]);
        $launcher->launch($run);

        return response()->json($this->payload($run->fresh()));
    }

    /** Agrega un dato corto del negocio a las notas que lee el analista. */
    public function addNote(Request $request): RedirectResponse
    {
        $validated = $request->validate(['nota' => ['required', 'string', 'min:5', 'max:'.AnalystNote::MAX_LENGTH]], [
            'nota.required' => 'Escribe el dato que quieres anotar.',
            'nota.min' => 'La nota es muy corta.',
            'nota.max' => 'La nota es muy larga (máximo '.AnalystNote::MAX_LENGTH.' caracteres): divídela en dos.',
        ]);
        $body = trim((string) preg_replace('/\s+/u', ' ', $validated['nota']));

        if (AnalystNote::count() >= AnalystNote::MAX_NOTES) {
            return back()->withInput()->withErrors(['nota' => 'Ya hay '.AnalystNote::MAX_NOTES.' notas: borra alguna antes de agregar otra.']);
        }
        if (AnalystNote::pluck('body')->contains(fn (string $n) => mb_strtolower($n) === mb_strtolower($body))) {
            return back()->withInput()->withErrors(['nota' => 'Esa nota ya está.']);
        }

        $note = AnalystNote::create(['body' => $body, 'source' => 'manual', 'created_by' => $request->user()->id]);
        AuditLog::record($request->user()->id, 'analista.nota', 'AnalystNote', $note->id, null, ['body' => $body, 'origen' => 'manual']);

        return back()->with('status', 'Nota agregada. El analista la usará desde la próxima pregunta.');
    }

    public function destroyNote(Request $request, AnalystNote $note): RedirectResponse
    {
        AuditLog::record($request->user()->id, 'analista.nota_borrar', 'AnalystNote', $note->id, ['body' => $note->body], null);
        $note->delete();

        return back()->with('status', 'Nota borrada.');
    }

    /** Contexto antiguo (texto libre): solo para quien ya lo había escrito. */
    public function saveContext(Request $request): RedirectResponse
    {
        $validated = $request->validate(['business_context' => ['nullable', 'string', 'max:8000']], [], ['business_context' => 'contexto del negocio']);
        $setting = AnalystSetting::current();
        $old = ['business_context' => $setting->business_context];
        $setting->update(['business_context' => trim((string) $validated['business_context']), 'updated_by' => $request->user()->id]);
        AuditLog::record($request->user()->id, 'analista.contexto', 'AnalystSetting', $setting->id, $old, ['business_context' => $setting->business_context]);

        return back()->with('status', 'Contexto del negocio guardado. El analista lo usará desde la próxima pregunta.');
    }

    public function destroy(Request $request, string $thread): RedirectResponse
    {
        abort_unless(Str::isUuid($thread), 404);
        $deleted = $this->visible($request)->where('thread', $thread)->delete();
        AuditLog::record($request->user()->id, 'analista.borrar_conversacion', 'AnalystRun', $thread, ['preguntas' => $deleted], null);

        return redirect()->route('admin.analyst.index')->with('status', 'Conversación eliminada.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $deleted = AnalystRun::query()->delete();
        AuditLog::record($request->user()->id, 'analista.borrar_todo', 'AnalystRun', 0, ['preguntas' => $deleted], null);

        return redirect()->route('admin.analyst.index')->with('status', 'Se eliminaron todas las conversaciones.');
    }

    public function show(Request $request, AnalystRun $run): JsonResponse
    {
        abort_unless($request->user()->isAdministrador() || $run->user_id === $request->user()->id, 403);

        // Si el proceso murió sin avisar, no dejar la página esperando para siempre.
        if (! $run->isFinished() && $run->updated_at->lt(now()->subMinutes(15))) {
            $run->update(['status' => 'error', 'error' => 'El análisis se interrumpió. Intenta de nuevo.']);
        }

        return response()->json($this->payload($run));
    }

    /** El administrador ve todas las conversaciones; los demás autorizados, solo las suyas. */
    private function visible(Request $request): Builder
    {
        return AnalystRun::query()->when(! $request->user()->isAdministrador(), fn ($q) => $q->where('user_id', $request->user()->id));
    }

    private function payload(AnalystRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'steps' => $run->steps ?? [],
            'answer_html' => $run->answer ? self::markdown($run->answer) : null,
            'error' => $run->error,
            'poll_url' => route('admin.analyst.show', $run),
        ];
    }

    public static function markdown(string $text): string
    {
        return Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
