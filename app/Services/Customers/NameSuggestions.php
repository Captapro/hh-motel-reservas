<?php

namespace App\Services\Customers;

use App\Models\BookingGuest;
use App\Models\Customer;
use App\Models\HistoricalGuest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Nombres y apellidos sueltos, de más a menos frecuentes, para sugerirlos
 * mientras recepción escribe. Primero los de quienes vinieron, repetidos al
 * menos dos veces, y después la lista base de resources/data. Son palabras
 * separadas: la lista no permite saber quién vino.
 */
class NameSuggestions
{
    private const MAX_PER_LIST = 4000;

    private const MIN_TIMES = 2;

    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'van', 'von', 'da', 'di', 'do', 'dos'];

    /** @return array{first: list<string>, last: list<string>} */
    public function lists(): array
    {
        return Cache::remember('customers.name_suggestions.v2', now()->addDay(), function () {
            $lists = $this->build($this->allNames());

            return [
                'first' => $this->withBase($lists['first'], 'nombres.txt'),
                'last' => $this->withBase($lists['last'], 'apellidos.txt'),
            ];
        });
    }

    /** @return array{first: list<string>, last: list<string>} */
    public function build(Collection $names): array
    {
        $first = [];
        $last = [];

        foreach ($names as $name) {
            $words = $this->words((string) $name);
            if (count($words) < 2) {
                continue;
            }
            // Con cuatro palabras la segunda es otro nombre; con tres, casi siempre apellido.
            $given = count($words) >= 4 ? 2 : 1;
            foreach ($words as $i => $word) {
                if ($i < $given) {
                    $first[$word] = ($first[$word] ?? 0) + 1;
                } else {
                    $last[$word] = ($last[$word] ?? 0) + 1;
                }
            }
        }

        return ['first' => $this->ranked($first), 'last' => $this->ranked($last)];
    }

    /** Agrega la lista base detrás de lo propio, sin repetir ("Gonzalez" = "González"). */
    public function withBase(array $own, string $file): array
    {
        $base = preg_split('/\s+/u', trim((string) file_get_contents(resource_path('data/'.$file))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = array_filter($base, fn (string $w) => preg_match('/^\p{L}{2,}$/u', $w) && ! in_array(mb_strtolower($w), self::PARTICLES, true));

        $seen = [];
        foreach ($own as $word) {
            $seen[Str::ascii($word)] = true;
        }

        // Si la base trae la forma con tilde, corrige la que se escribió sin ella.
        $accented = [];
        foreach ($base as $word) {
            if ($word !== Str::ascii($word)) {
                $accented[Str::ascii($word)] = $word;
            }
        }
        $own = array_map(fn (string $w) => $accented[$w] ?? $w, $own);

        $merged = $own;
        foreach ($base as $word) {
            $key = Str::ascii($word);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $merged[] = $accented[$key] ?? $word;
            }
        }

        return array_slice($merged, 0, self::MAX_PER_LIST);
    }

    private function allNames(): Collection
    {
        return Customer::query()->whereNotNull('name')->pluck('name')
            ->merge(BookingGuest::query()->pluck('name'))
            ->merge(HistoricalGuest::query()->whereNotNull('name')->pluck('name'));
    }

    /** @return list<string> */
    private function words(string $name): array
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_filter($words, fn (string $w) => mb_strlen($w) >= 2
            && preg_match('/^\p{L}+$/u', $w)
            && ! in_array(mb_strtolower($w), self::PARTICLES, true));

        return array_values(array_map(fn (string $w) => mb_convert_case(mb_strtolower($w), MB_CASE_TITLE), $words));
    }

    /** "Gonzalez" y "González" son el mismo: se suman y queda la forma con tilde. */
    private function mergeAccents(array $counts): array
    {
        $merged = [];
        foreach (collect($counts)->groupBy(fn ($times, $word) => Str::ascii((string) $word), true) as $variants) {
            $words = array_map('strval', $variants->keys()->all());
            $accented = array_values(array_filter($words, fn (string $w) => $w !== Str::ascii($w)));
            $merged[$accented[0] ?? $words[0]] = $variants->sum();
        }

        return $merged;
    }

    /** @return list<string> */
    private function ranked(array $counts): array
    {
        $counts = $this->mergeAccents($counts);
        $counts = array_filter($counts, fn (int $times) => $times >= self::MIN_TIMES);
        arsort($counts);

        return array_map('strval', array_slice(array_keys($counts), 0, self::MAX_PER_LIST));
    }
}
