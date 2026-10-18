<?php

declare(strict_types=1);

use App\Services\Semaforo\RevisorDeSemaforo;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Collection;

/*
|--------------------------------------------------------------------------
| Consola y agenda
|--------------------------------------------------------------------------
*/

Artisan::command('notaria:inspirar', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Mensaje de ejemplo, como el que trae Laravel recien creado');

/**
 * Corre el semaforo para TODAS las notarias.
 *
 * El contexto se fija por notaria y se descarta al terminar cada una
 * (alcance de transaccion). Por eso un mismo worker puede revisar todas sin
 * que una arrastre el contexto de la anterior.
 */
Artisan::command('notaria:revisar-semaforo {--notaria= : Revisar solo una notaria, por identificador}', function (): void {
    $contexto = new ContextoTenant();
    $revisor = new RevisorDeSemaforo();

    $notarias = \App\Models\Notaria::query()
        ->when($this->option('notaria'), fn ($consulta) => $consulta->where('id', $this->option('notaria')))
        ->where('activo', true)
        ->get();

    foreach ($notarias as $notaria) {
        $atrasadas = $contexto->ejecutarAislado(
            $notaria->id,
            fn (): Collection => $revisor->revisar()
        );

        $this->info(sprintf(
            '%s: %d etapa(s) con el semaforo en amarillo o rojo.',
            $notaria->nombre,
            $atrasadas->count()
        ));
    }
})->purpose('Recorre las etapas en espera y actualiza el semaforo y las alertas de todas las notarias.');

/*
| El semaforo, todos los dias.
|
| Se corre temprano para que el tablero de la manana ya muestre lo que
| cambio mientras la oficina estaba cerrada. Ademas, la pantalla del tablero
| corre una pasada fresca al abrirse, asi que un cron que se atraso no
| significa un tablero desactualizado.
|
| El recordatorio del lunes es porque el lunes es cuando mas se acumulan
| los expedientes que quedaron parados el viernes.
*/
Schedule::command('notaria:revisar-semaforo')
    ->dailyAt('07:10')
    ->withoutOverlapping();

Schedule::command('notaria:revisar-semaforo')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping();