<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Customers\IdCardReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carnet por foto (piloto): recepción saca una foto con el celular y se completan RUN, nombre y apellidos.
 * La imagen no se guarda en ningún lado; en la bitácora queda solo que se usó, sin datos de la persona.
 */
class IdCardPhotoController extends Controller
{
    public function read(Request $request, IdCardReader $reader): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:6144']], [
            'photo.required' => 'Falta la foto del carnet.',
            'photo.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'photo.max' => 'La foto pesa demasiado. Toma otra.',
        ]);

        $file = $request->file('photo');
        $result = $reader->read((string) file_get_contents($file->getRealPath()), $this->mediaType($file->getMimeType()));

        AuditLog::record($request->user()?->id, 'carnet.foto', 'IdCardPhoto', 0, null, ['ok' => $result['ok']]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    private function mediaType(?string $mime): string
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'image/jpeg';
    }
}
