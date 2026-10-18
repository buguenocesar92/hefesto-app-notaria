<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de la siembra.
 *
 * Se ejecuta con:  php artisan db:seed
 *
 * Todo lo de desarrollo va en `NotariaDemoSeeder`. Ver el docblock de esa
 * clase, que explica por que crea dos notarias y por que los umbrales del
 * semaforo arrancan vacios.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            NotariaDemoSeeder::class,
        ]);
    }
}