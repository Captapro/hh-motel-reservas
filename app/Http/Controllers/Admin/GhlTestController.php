<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Integrations\GhlClient;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Pantalla de diagnóstico para probar la conexión con GHL contra un
 * contacto real (de prueba) antes de confiar en que la sincronización
 * automática de reservas (GhlBookingSync) funciona -- sin esto, la única
 * forma de saber si el token/location ID están bien sería esperar a que
 * entre una reserva real y revisar los logs.
 */
class GhlTestController extends Controller
{
    public function index(GhlClient $client): View
    {
        return view('admin.ghl.index', ['configured' => $client->isConfigured()]);
    }

    public function test(Request $request, GhlClient $client): View
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $phone = Phone::toE164($validated['phone']);
        $log = [];
        $error = null;

        try {
            $log[] = "Buscando contacto existente con teléfono {$phone}...";
            $existingId = $client->findContactIdByPhone($phone);
            $log[] = $existingId ? "Encontrado: {$existingId}" : 'No existía -- se va a crear uno nuevo.';

            $contactId = $client->upsertContact($phone, $validated['name']);
            $log[] = "Contacto en GHL: {$contactId}";

            $tag = GhlBookingSync::TAG_PREFIX.now()->timezone('America/Santiago')->toDateString();
            $client->replaceTagWithPrefix($contactId, GhlBookingSync::TAG_PREFIX, $tag);
            $log[] = "Tag aplicado: {$tag}";

            $tags = $client->getTags($contactId);
            $log[] = 'Tags actuales del contacto: '.(empty($tags) ? '(ninguno)' : implode(', ', $tags));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.ghl.index', [
            'configured' => $client->isConfigured(),
            'log' => $log,
            'error' => $error,
            'testedPhone' => $phone,
        ]);
    }
}
