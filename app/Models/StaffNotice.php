<?php

namespace App\Models;

use App\Services\Notifications\AdminPush;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Aviso que se abre encima de todo hasta confirmar "Entendido": de la administración
 * al personal, o del sistema a la administración ('administracion').
 */
#[Fillable(['user_id', 'audience', 'urgent', 'body'])]
class StaffNotice extends Model
{
    public const AUDIENCES = ['todos' => 'Todo el personal', 'anfitriones' => 'Anfitriones', 'mucamas' => 'Mucamas'];

    /** Quien entra a su turno alcanza a ver los avisos del último día. */
    public const VISIBLE_HOURS = 24;

    protected function casts(): array
    {
        return ['urgent' => 'boolean'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(StaffNoticeRead::class);
    }

    /** Alerta a los administradores en pantalla y en el celular. */
    public static function alertAdmins(int $userId, string $title, string $detail): void
    {
        self::create(['user_id' => $userId, 'audience' => 'administracion', 'urgent' => true, 'body' => $title.'. '.$detail]);
        AdminPush::send($title, $detail);
    }

    /** Lo que aún no confirma esta anfitriona o esta mucama. Vacío si la tabla aún no existe. */
    public static function pendingFor(User|Staff $viewer): Collection
    {
        $isUser = $viewer instanceof User;
        $audiences = ! $isUser ? ['todos', 'mucamas'] : ($viewer->isAdministrador() ? ['administracion'] : ['todos', 'anfitriones']);

        try {
            return self::with('sender')
                ->whereIn('audience', $audiences)
                ->where('created_at', '>=', now()->subHours(self::VISIBLE_HOURS))
                ->whereDoesntHave('reads', fn (Builder $q) => $q->where($isUser ? 'user_id' : 'staff_id', $viewer->id))
                ->orderByDesc('urgent')->orderBy('created_at')->get();
        } catch (QueryException) {
            return collect();
        }
    }

    /** Nombres de quienes deben recibirlo y de quienes ya lo confirmaron. */
    public function delivery(): array
    {
        $hosts = $this->audience === 'mucamas' ? collect() : User::where('role', 'recepcion')->where('is_active', true)->get(['id', 'name']);
        $maids = $this->audience === 'anfitriones' ? collect() : Staff::activeMucamas()->get(['id', 'name']);
        $readHosts = $this->reads->pluck('user_id')->filter();
        $readMaids = $this->reads->pluck('staff_id')->filter();

        return [
            'read' => $hosts->whereIn('id', $readHosts)->pluck('name')->merge($maids->whereIn('id', $readMaids)->pluck('name'))->values(),
            'missing' => $hosts->whereNotIn('id', $readHosts)->pluck('name')->merge($maids->whereNotIn('id', $readMaids)->pluck('name'))->values(),
        ];
    }
}
