<?php

namespace App\Support;

/**
 * Un acompañante viaja como una línea de texto: "Nombre Apellido" o, con documento,
 * "Nombre Apellido | RUT". El documento (RUT o pasaporte) es opcional.
 */
class GuestLine
{
    /** @return array{name: string, document: ?string} */
    public static function parse(string $line): array
    {
        [$name, $document] = array_pad(explode('|', $line, 2), 2, '');
        $document = mb_strtoupper(trim($document));

        return [
            'name' => trim($name),
            'document' => $document !== '' ? mb_substr($document, 0, 20) : null,
        ];
    }

    public static function format(string $name, ?string $document): string
    {
        return $document ? $name.' | '.$document : $name;
    }
}
