<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThrottlePrefixTest extends TestCase
{
    /**
     * Sin prefijo, todos los `throttle:N,M` de una misma IP comparten un
     * único contador: las imágenes y consultas del catálogo agotaban el
     * límite del envío de la reserva y el cliente veía un 429.
     */
    public function test_cada_limite_de_peticiones_tiene_su_propio_contador(): void
    {
        $prefixes = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'throttle:')) {
                    continue;
                }

                $parts = explode(',', substr($middleware, strlen('throttle:')));

                $this->assertCount(3, $parts, "{$route->uri()} usa {$middleware} sin prefijo propio");
                $this->assertNotContains($parts[2], $prefixes, "Prefijo repetido: {$parts[2]}");
                $prefixes[] = $parts[2];
            }
        }

        $this->assertNotEmpty($prefixes);
    }
}
