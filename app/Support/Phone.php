<?php

namespace App\Support;

/**
 * Normaliza un teléfono a E.164 chileno (+56XXXXXXXXX). El número de
 * WhatsApp es la identidad del cliente — misma normalización en todos lados
 * (crear reserva, buscar cliente, sync a GHL) para que siempre matchee.
 */
class Phone
{
    /** Códigos de país de 1 dígito (1 = EE.UU./Canadá, 7 = Rusia/Kazajistán). */
    private const CC1 = ['1', '7'];

    /** Códigos de país de 2 dígitos. */
    private const CC2 = [
        '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45', '46', '47', '48', '49',
        '51', '52', '53', '54', '55', '56', '57', '58', '60', '61', '62', '63', '64', '65', '66', '81', '82', '84',
        '86', '90', '91', '92', '93', '94', '95', '98',
    ];

    /** Códigos de país de 3 dígitos (los asignados). */
    private const CC3 = [
        '211', '212', '213', '216', '218', '220', '221', '222', '223', '224', '225', '226', '227', '228', '229',
        '230', '231', '232', '233', '234', '235', '236', '237', '238', '239', '240', '241', '242', '243', '244',
        '245', '246', '247', '248', '249', '250', '251', '252', '253', '254', '255', '256', '257', '258', '260',
        '261', '262', '263', '264', '265', '266', '267', '268', '269', '290', '291', '297', '298', '299',
        '350', '351', '352', '353', '354', '355', '356', '357', '358', '359', '370', '371', '372', '373', '374',
        '375', '376', '377', '378', '379', '380', '381', '382', '383', '385', '386', '387', '389', '420', '421',
        '423', '500', '501', '502', '503', '504', '505', '506', '507', '508', '509', '590', '591', '592', '593',
        '594', '595', '596', '597', '598', '599', '670', '672', '673', '674', '675', '676', '677', '678', '679',
        '680', '681', '682', '683', '685', '686', '687', '688', '689', '690', '691', '692', '850', '852', '853',
        '855', '856', '880', '886', '960', '961', '962', '963', '964', '965', '966', '967', '968', '970', '971',
        '972', '973', '974', '975', '976', '977', '992', '993', '994', '995', '996', '998',
    ];

    public static function toE164(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        // 9 dígitos sin prefijo país => celular chileno, se antepone 56.
        if (! str_starts_with($digits, '56') && strlen($digits) === 9) {
            $digits = '56'.$digits;
        }

        return '+'.$digits;
    }

    /**
     * ¿Tiene pinta de número completo? Se usa para no disparar la búsqueda
     * mientras todavía están tipeando/pegando.
     */
    public static function looksComplete(string $raw): bool
    {
        return strlen(preg_replace('/\D/', '', $raw)) >= 9;
    }

    public static function isValid(string $raw): bool
    {
        return self::problem($raw) === null;
    }

    /**
     * Qué está mal en el teléfono, en palabras para recepción; null si está bien. Un celular chileno
     * son 9 dígitos (+56 9 XXXX XXXX); los extranjeros se escriben con + y el código de país.
     */
    public static function problem(string $raw): ?string
    {
        $raw = trim($raw);
        $explicitPlus = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }
        $n = strlen($digits);

        if ($n === 0) {
            return 'Falta el teléfono.';
        }

        // Chileno sin código de país: 9 dígitos, el primero de 2 a 9 (2 = fijo de Santiago, 9 = celular).
        if (! $explicitPlus && ! str_starts_with($digits, '56')) {
            if ($n === 9) {
                return $digits[0] >= '2' ? null : 'Un número chileno de 9 dígitos parte con 9 (celular) o con el código de área (ej. 2 para Santiago).';
            }
            if ($n < 9) {
                return "Le faltan dígitos: tiene {$n} y un celular chileno tiene 9 (ej. 9 1234 5678). Si es extranjero, escríbelo con + y el código de país.";
            }
            if ($n === 10 || $n > 11) {
                return "Tiene {$n} dígitos: un celular chileno tiene 9 (ej. 9 1234 5678). Si es extranjero, escríbelo con + y el código de país.";
            }
        }

        if (str_starts_with($digits, '56')) {
            if ($n !== 11) {
                return $n < 11
                    ? 'Al número chileno le faltan '.(11 - $n).' dígito(s): debe ser +56 y 9 dígitos (ej. +56 9 1234 5678).'
                    : 'El número chileno tiene '.($n - 11).' dígito(s) de más: debe ser +56 y 9 dígitos (ej. +56 9 1234 5678).';
            }

            return $digits[2] >= '2' ? null : 'Después del +56 el número debe partir con 9 (celular) o con el código de área.';
        }

        // Extranjero: con +, código de país real y largo razonable (E.164: hasta 15 dígitos).
        if (! $explicitPlus) {
            return 'Si el número es extranjero, escríbelo con + y el código de país (ej. +54 9 11 1234 5678).';
        }
        if ($n < 9 || $n > 15 || ! self::hasKnownCountryCode($digits)) {
            return 'El código de país o el largo del número no parecen válidos. Si es chileno, debe ser +56 y 9 dígitos (ej. +56 9 1234 5678).';
        }

        return null;
    }

    private static function hasKnownCountryCode(string $digits): bool
    {
        return in_array(substr($digits, 0, 1), self::CC1, true)
            || in_array(substr($digits, 0, 2), self::CC2, true)
            || in_array(substr($digits, 0, 3), self::CC3, true);
    }
}
