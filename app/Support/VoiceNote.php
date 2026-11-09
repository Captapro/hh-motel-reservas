<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Nota de voz grabada en la bandeja. Chrome graba en webm, que WhatsApp no acepta; se pasa a
 * ogg/opus (el formato de las notas de voz de WhatsApp) con ffmpeg. Normalmente basta con
 * cambiar el envoltorio sin volver a codificar (instantáneo); si no se puede, se recodifica.
 * Safari graba en m4a y Firefox en ogg: esos ya sirven tal cual.
 */
class VoiceNote
{
    public const CONVERT_EXTENSIONS = ['webm'];

    public static function needsConversion(UploadedFile $file): bool
    {
        return in_array(strtolower($file->getClientOriginalExtension()), self::CONVERT_EXTENSIONS, true);
    }

    public static function toOgg(UploadedFile $file): UploadedFile
    {
        $base = tempnam(sys_get_temp_dir(), 'nota-');
        @unlink($base);
        $out = $base.'.ogg';
        $in = $file->getRealPath();

        foreach ([['-c:a', 'copy'], ['-c:a', 'libopus', '-b:a', '32k']] as $codec) {
            $result = Process::timeout(30)->run(array_merge(['ffmpeg', '-nostdin', '-loglevel', 'error', '-y', '-i', $in, '-vn'], $codec, [$out]));
            if ($result->successful() && is_file($out) && filesize($out) > 0) {
                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'nota-de-voz';

                return new UploadedFile($out, $name.'.ogg', 'audio/ogg', null, true);
            }
        }

        throw new RuntimeException('No se pudo preparar la nota de voz. Intenta de nuevo o adjunta el audio.');
    }
}
