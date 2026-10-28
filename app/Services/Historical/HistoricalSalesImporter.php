<?php

namespace App\Services\Historical;

use App\Models\HistoricalSale;
use App\Support\Rut;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga una planilla de ventas de recepción (exportada a CSV) en las tablas de
 * ventas históricas. Cada fila con tipo de venta es una línea de venta; las
 * filas siguientes de una estadía, sin tipo pero con nombre, son los
 * acompañantes. Las filas de resumen, ajustes y blancos se ignoran.
 *
 * Es repetible: una línea ya cargada (misma planilla y N° de registro) se
 * actualiza en vez de duplicarse.
 */
class HistoricalSalesImporter
{
    private const SALE_TYPES = ['ALOJAMIENTO', 'EXTRAS', 'EXTRA CORE', 'PAGO FRACCIONADO', 'EVENTOS'];

    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    /** Patrón del encabezado -> nombre interno de la columna. */
    private const HEADERS = [
        'registro' => '/registro/iu',
        'fecha' => '/^fecha/iu',
        'doc_type' => '/tipo de documento/iu',
        'voucher' => '/n.\s*voucher/iu',
        'receipt' => '/n.\s*boleta/iu',
        'sale_type' => '/tipo de venta/iu',
        'detail' => '/^detalle:/iu',
        'quantity' => '/^cantidad$/iu',
        'name' => '/^nombre/iu',
        'document' => '/rut o documento/iu',
        'nationality' => '/nacionalidad/iu',
        'age' => '/^edad/iu',
        'guests' => '/n.\s*de personas/iu',
        'room' => '/^habitaci[oó]n$/iu',
        'hours_paid' => '/formato de tiempo/iu',
        'starts' => '/hora ingreso/iu',
        'ends' => '/hora salida/iu',
        'duration' => '/cantidad de horas/iu',
        'category' => '/tipo habitaci[oó]n/iu',
        'discount' => '/^descuento/iu',
        'amount_extras' => '/venta de extras/iu',
        'amount_main' => '/venta de alojamiento/iu',
        'payment' => '/medio de pago/iu',
        'reservation' => '/^reserva$/iu',
        'notes' => '/observaciones/iu',
        'shift' => '/^turno/iu',
    ];

    /**
     * @return array<string, mixed> resumen de lo leído (sin datos personales)
     */
    public function import(string $path, string $source, int $defaultYear, bool $dryRun = false): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException("No se pudo abrir {$path}");
        }

        $columns = null;
        $lines = [];
        $current = null;      // índice de la estadía abierta en $lines
        $ignored = 0;
        $inferred = 0;
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($columns === null) {
                $columns = $this->mapColumns($row);

                continue;
            }

            $date = $this->parseDate($this->cell($row, $columns, 'fecha'), $defaultYear);
            $type = mb_strtoupper($this->cell($row, $columns, 'sale_type'));
            $name = $this->cleanName($this->cell($row, $columns, 'name'));

            // Una fila con fecha y monto del alojamiento pero sin tipo de venta es una
            // estadía a la que se le olvidó el tipo: se cuenta como alojamiento.
            if ($type === '' && $date && $this->money($this->cell($row, $columns, 'amount_main')) > 0) {
                $type = 'ALOJAMIENTO';
                $inferred++;
            }

            if (in_array($type, self::SALE_TYPES, true) && $date) {
                $lines[] = $this->saleLine($row, $columns, $date, $type, $rowNumber, $name);
                $current = $type === 'ALOJAMIENTO' ? array_key_last($lines) : null;

                continue;
            }

            // Acompañante: sin tipo de venta, con nombre, y de la misma estadía abierta.
            if ($type === '' && $name !== '' && $current !== null && (! $date || $date->equalTo($lines[$current]['sold_on']))) {
                $lines[$current]['guests'][] = $this->guest($row, $columns, false, $name);

                continue;
            }

            if ($this->rowHasContent($row)) {
                $ignored++;
            }
        }
        fclose($handle);

        $summary = $this->summarize($lines, $ignored) + ['tipo_inferido' => $inferred];

        if (! $dryRun) {
            DB::transaction(function () use ($lines, $source) {
                foreach ($lines as $line) {
                    $guests = $line['guests'];
                    unset($line['guests']);
                    $sale = HistoricalSale::updateOrCreate(['source' => $source, 'source_row' => $line['source_row']], $line);
                    $sale->guests()->delete();
                    foreach ($guests as $guest) {
                        $sale->guests()->create($guest);
                    }
                }
            });
        }

        return $summary;
    }

    /** @return array<string, int> nombre interno => posición de la columna */
    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            foreach (self::HEADERS as $key => $pattern) {
                if (! isset($map[$key]) && preg_match($pattern, $title)) {
                    $map[$key] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    private function cell(array $row, array $columns, string $key): string
    {
        return isset($columns[$key]) ? trim((string) ($row[$columns[$key]] ?? '')) : '';
    }

    private function rowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function saleLine(array $row, array $columns, Carbon $date, string $type, int $rowNumber, string $name): array
    {
        $registro = $this->cell($row, $columns, 'registro');
        $category = mb_strtoupper($this->cell($row, $columns, 'category'));
        $shift = mb_strtoupper($this->cell($row, $columns, 'shift'));

        $line = [
            'source_row' => ctype_digit($registro) ? (int) $registro : 1000000 + $rowNumber,
            'sold_on' => $date,
            'sale_type' => $type,
            'doc_type' => $this->nullIfEmpty($this->cell($row, $columns, 'doc_type')),
            'voucher_number' => $this->nullIfEmpty($this->cell($row, $columns, 'voucher')),
            'receipt_number' => $this->nullIfEmpty($this->cell($row, $columns, 'receipt')),
            'detail' => $this->nullIfEmpty($this->cell($row, $columns, 'detail')),
            'quantity' => $this->intOrNull($this->cell($row, $columns, 'quantity')),
            'room_label' => $this->nullIfEmpty($this->cell($row, $columns, 'room')),
            'category' => preg_match('/^[A-ZÁÉÍÓÚÑ ]{1,30}$/u', $category) ? $category : null,
            'guests_count' => $this->intOrNull($this->cell($row, $columns, 'guests')),
            'hours_paid' => $this->intOrNull($this->cell($row, $columns, 'hours_paid')),
            'starts_time' => $this->timeOrNull($this->cell($row, $columns, 'starts')),
            'ends_time' => $this->timeOrNull($this->cell($row, $columns, 'ends')),
            'duration_minutes' => $this->minutes($this->cell($row, $columns, 'duration')),
            'discount_note' => $this->nullIfEmpty(mb_substr($this->cell($row, $columns, 'discount'), 0, 120)),
            'amount_main' => $this->money($this->cell($row, $columns, 'amount_main')),
            'amount_extras' => $this->money($this->cell($row, $columns, 'amount_extras')),
            'payment_method' => $this->payment($this->cell($row, $columns, 'payment')),
            'reservation_ref' => $this->nullIfEmpty(mb_substr($this->cell($row, $columns, 'reservation'), 0, 60)),
            'notes' => $this->nullIfEmpty($this->cell($row, $columns, 'notes')),
            'shift' => in_array($shift, ['MAÑANA', 'TARDE', 'NOCHE'], true) ? $shift : null,
            'guests' => [],
        ];

        // El titular va en la misma fila de la estadía.
        if ($type === 'ALOJAMIENTO' && $name !== '') {
            $line['guests'][] = $this->guest($row, $columns, true, $name);
        }

        return $line;
    }

    private function guest(array $row, array $columns, bool $holder, string $name): array
    {
        [$document, $kind] = $this->document($this->cell($row, $columns, 'document'));
        $age = $this->intOrNull($this->cell($row, $columns, 'age'));
        $nationality = mb_strtoupper($this->cell($row, $columns, 'nationality'));

        return [
            'name' => mb_substr($name, 0, 160),
            'document' => $document,
            'document_kind' => $kind,
            'nationality' => $nationality !== '' ? mb_substr($nationality, 0, 60) : null,
            'age' => $age !== null && $age > 0 && $age < 120 ? $age : null,
            'is_holder' => $holder,
        ];
    }

    /** @return array{0: ?string, 1: ?string} documento normalizado y su tipo (rut, rut_invalido, otro) */
    private function document(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, null];
        }

        // Solo dígitos (y una K al final) puede ser un RUT; con otras letras es un pasaporte u otro documento.
        $compact = preg_replace('/[\.\-\s]/', '', $raw);
        if (preg_match('/^\d{6,9}[\dkK]$/', $compact)) {
            $rut = Rut::normalize($compact);

            return [$rut, Rut::isValid($rut) ? 'rut' : 'rut_invalido'];
        }

        return [mb_strtoupper(mb_substr($raw, 0, 30)), 'otro'];
    }

    private function parseDate(string $raw, int $defaultYear): ?Carbon
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $raw, $m)) {
            $year = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];

            return checkdate((int) $m[2], (int) $m[1], $year) ? Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay() : null;
        }
        if (preg_match('/^(\d{1,2})[\s\-]*(?:de\s+)?([a-záéíóú]+)(?:[\s\-]*(?:de\s+)?(\d{4}))?$/u', $raw, $m)) {
            $month = self::MONTHS[$m[2]] ?? null;
            $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : $defaultYear;

            return $month && checkdate($month, (int) $m[1], $year) ? Carbon::create($year, $month, (int) $m[1])->startOfDay() : null;
        }

        return null;
    }

    private function money(string $raw): int
    {
        $digits = preg_replace('/[^\d]/', '', $raw);

        return $digits === '' ? 0 : (int) $digits;
    }

    private function intOrNull(string $raw): ?int
    {
        return preg_match('/^\d{1,5}$/', trim($raw)) ? (int) $raw : null;
    }

    private function timeOrNull(string $raw): ?string
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', trim($raw), $m) ? sprintf('%02d:%s', (int) $m[1], $m[2]) : null;
    }

    private function minutes(string $raw): ?int
    {
        return preg_match('/^(\d{1,3}):(\d{2})/', trim($raw), $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
    }

    private function payment(string $raw): ?string
    {
        $value = mb_strtolower(trim($raw));

        return in_array($value, ['tarjeta', 'efectivo', 'transferencia'], true) ? $value : null;
    }

    private function nullIfEmpty(string $raw): ?string
    {
        $raw = trim($raw);

        return $raw === '' ? null : $raw;
    }

    private function cleanName(string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', $raw));
    }

    private function summarize(array $lines, int $ignored): array
    {
        $byType = [];
        $guests = 0;
        $kinds = ['rut' => 0, 'rut_invalido' => 0, 'otro' => 0, 'sin_documento' => 0];
        $dates = [];
        foreach ($lines as $line) {
            $t = $line['sale_type'];
            $byType[$t] ??= ['lineas' => 0, 'principal' => 0, 'extras' => 0];
            $byType[$t]['lineas']++;
            $byType[$t]['principal'] += $line['amount_main'];
            $byType[$t]['extras'] += $line['amount_extras'];
            $dates[] = $line['sold_on'];
            foreach ($line['guests'] as $g) {
                $guests++;
                $kinds[$g['document_kind'] ?? 'sin_documento']++;
            }
        }
        sort($dates);

        return [
            'lineas' => count($lines),
            'por_tipo' => $byType,
            'venta_principal' => array_sum(array_column($byType, 'principal')),
            'venta_extras' => array_sum(array_column($byType, 'extras')),
            'huespedes' => $guests,
            'documentos' => $kinds,
            'desde' => $dates ? $dates[0]->toDateString() : null,
            'hasta' => $dates ? end($dates)->toDateString() : null,
            'filas_ignoradas' => $ignored,
        ];
    }
}
