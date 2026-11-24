<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Customers\IdCardReader;
use App\Services\Customers\IdScanSession;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Celular como cámara del computador: el computador pide una sesión y muestra su QR; el celular abre
 * la página del QR (sin login, el código de un solo uso es el permiso) y manda fotos de carnets; el
 * computador las recoge y llena el formulario. El código solo permite mandar fotos de carnet a leer.
 */
class IdScanSessionController extends Controller
{
    // ---- Computador (con sesión iniciada) ----

    public function create(Request $request, IdScanSession $sessions): JsonResponse
    {
        $token = $sessions->create((int) $request->user()->id);

        return response()->json([
            'token' => $token,
            'qr' => route('id_scan.qr', $token),
            'poll' => route('id_scan.poll', $token),
            'close' => route('id_scan.close', $token),
            'expires_in' => IdScanSession::TTL_SECONDS,
        ]);
    }

    public function qr(Request $request, string $token, IdScanSession $sessions): Response
    {
        abort_unless($sessions->owner($token) === (int) $request->user()->id, 404);
        $result = (new Builder(writer: new PngWriter, data: route('id_scan.phone', $token), size: 360, margin: 8))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType(), 'Cache-Control' => 'no-store']);
    }

    public function poll(Request $request, string $token, IdScanSession $sessions): JsonResponse
    {
        $taken = $sessions->takeAfter($token, (int) $request->user()->id, max(0, (int) $request->query('after', 0)));
        if ($taken === null) {
            return response()->json(['expired' => true], 410);
        }

        return response()->json($taken + ['expired' => false]);
    }

    public function close(Request $request, string $token, IdScanSession $sessions): JsonResponse
    {
        $sessions->close($token, (int) $request->user()->id);

        return response()->json(['ok' => true]);
    }

    // ---- Celular (sin login: el código de la sesión es el permiso) ----

    public function phone(string $token, IdScanSession $sessions): View
    {
        return view('id-scan.phone', ['token' => $token, 'valid' => $sessions->exists($token)]);
    }

    public function upload(Request $request, string $token, IdScanSession $sessions, IdCardReader $reader): JsonResponse
    {
        $owner = $sessions->owner($token);
        if ($owner === null) {
            return response()->json(['ok' => false, 'error' => 'La sesión venció. Genera otro QR en el computador.'], 410);
        }

        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:6144']], [
            'photo.required' => 'Falta la foto del carnet.',
            'photo.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'photo.max' => 'La foto pesa demasiado. Toma otra.',
        ]);

        $file = $request->file('photo');
        $mime = in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) ? $file->getMimeType() : 'image/jpeg';
        $result = $reader->read((string) file_get_contents($file->getRealPath()), $mime);

        AuditLog::record($owner, 'carnet.foto', 'IdCardPhoto', 0, null, ['ok' => $result['ok'], 'via' => 'qr']);
        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        $n = $sessions->addRead($token, $result);
        if ($n === null) {
            return response()->json(['ok' => false, 'error' => 'La sesión venció o ya leyó todos los carnets posibles. Genera otro QR.'], 410);
        }

        return response()->json(['ok' => true, 'n' => $n, 'name' => trim(($result['first_name'] ?? '').' '.($result['last_name'] ?? ''))]);
    }
}
