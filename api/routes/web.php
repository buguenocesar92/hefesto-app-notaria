<?php

declare(strict_types=1);

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AntecedenteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CobranzaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ParteController;
use App\Http\Controllers\TableroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| POR QUE ESTAS RUTAS ESTAN EN `web.php` Y NO EN `api.php`
| -------------------------------------------------------
| Porque van en el grupo `web`, que es donde estan `ResolverTenant` y
| `AuditarAcceso`. `ResolverTenant` lee el usuario con el guard `web`, o sea
| el de la sesion con cookie: si estas rutas estuvieran en el grupo `api`, que
| por omision no lleva sesion ni cookie, no habria a quien asignarle una
| notaria y la RLS abortaria todas las peticiones.
|
| El prefijo `/api/v1` se pone a mano, y el BFF de Nuxt reenvia `/api/**`
| aca. Ver docs/01.
|
| Todas tienen sesion y todas corren dentro de la transaccion que fija la
| notaria.
|
| DOS AUSENCIAS DELIBERADAS
| -------------------------
| No hay ninguna ruta DELETE de documentos. Un documento de un expediente no
| se borra: se reemplaza. Ver `DocumentoController`.
|
| No hay ninguna ruta DELETE de expedientes. Un expediente mal abierto se
| anula.
|
| Cuando una Policy rechaza, la respuesta es 404 y no 403: un 403 confirmaria
| que el recurso existe.
|
*/

// --- Sin sesion: solo la pantalla de entrada -------------------------------
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('auth/notarias', [AuthController::class, 'notarias'])->name('auth.notarias');
    Route::get('auth/entrar/{slug}', [AuthController::class, 'entrar'])->name('auth.entrar');
    Route::get('auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

    Route::get('salud', fn () => response()->json([
        'estado' => 'ok',
        'servicio' => 'api-notaria',
    ]))->name('salud');
});

// --- Con sesion -----------------------------------------------------------
Route::prefix('v1')->name('api.v1.')->middleware('auth')->group(function (): void {
    Route::post('auth/salir', [AuthController::class, 'salir'])->name('auth.salir');

    // El tablero: la pantalla que justifica el producto.
    Route::get('tablero', [TableroController::class, 'index'])->name('tablero');

    // Expedientes.
    Route::get('expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
    Route::post('expedientes', [ExpedienteController::class, 'store'])->name('expedientes.store');
    Route::get('expedientes/{expediente}', [ExpedienteController::class, 'show'])->name('expedientes.show');
    Route::patch('expedientes/{expediente}', [ExpedienteController::class, 'update'])->name('expedientes.update');
    Route::post('expedientes/{expediente}/cerrar', [ExpedienteController::class, 'cerrar'])->name('expedientes.cerrar');
    Route::post('expedientes/{expediente}/anular', [ExpedienteController::class, 'anular'])->name('expedientes.anular');

    Route::get('expedientes/{expediente}/etapas', [TableroController::class, 'etapasDeExpediente'])->name('expedientes.etapas');
    Route::post('expedientes/{expediente}/etapas/{etapa}/estado', [ExpedienteController::class, 'cambiarEstadoEtapa'])->name('expedientes.etapas.estado');
    Route::patch('expedientes/{expediente}/etapas/{etapa}/umbrales', [ExpedienteController::class, 'cambiarUmbrales'])->name('expedientes.etapas.umbrales');

    // Antecedentes.
    Route::get('expedientes/{expediente}/antecedentes', [AntecedenteController::class, 'index'])->name('antecedentes.index');
    Route::post('expedientes/{expediente}/antecedentes', [AntecedenteController::class, 'store'])->name('antecedentes.store');
    Route::patch('antecedentes/{antecedente}', [AntecedenteController::class, 'update'])->name('antecedentes.update');

    // Documentos. NOTA: no hay DELETE. Nunca lo hubo.
    Route::get('expedientes/{expediente}/documentos', [DocumentoController::class, 'index'])->name('documentos.index');
    Route::post('expedientes/{expediente}/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
    Route::post('documentos/{documento}/versiones', [DocumentoController::class, 'agregarVersion'])->name('documentos.versiones');
    Route::post('documentos/{documento}/reemplazar', [DocumentoController::class, 'marcarReemplazado'])->name('documentos.reemplazar');
    Route::get('documentos/{documento}/versiones/{version}/descargar', [DocumentoController::class, 'descargar'])->name('documentos.descargar');

    // Cobranzas (registro, NO emision de documentos tributarios).
    Route::get('expedientes/{expediente}/cobranzas', [CobranzaController::class, 'index'])->name('cobranzas.index');
    Route::post('expedientes/{expediente}/cobranzas', [CobranzaController::class, 'store'])->name('cobranzas.store');
    Route::patch('cobranzas/{cobranza}', [CobranzaController::class, 'update'])->name('cobranzas.update');

    // Partes. El rol se asigna EN un expediente, no a la persona.
    Route::get('partes', [ParteController::class, 'index'])->name('partes.index');
    Route::post('partes', [ParteController::class, 'store'])->name('partes.store');
    Route::patch('partes/{parte}', [ParteController::class, 'update'])->name('partes.update');
    Route::post('partes/{parte}/roles', [ParteController::class, 'asignarRol'])->name('partes.roles');

    // Agenda.
    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::post('agenda', [AgendaController::class, 'store'])->name('agenda.store');
    Route::patch('agenda/{cita}', [AgendaController::class, 'update'])->name('agenda.update');
    Route::post('agenda/{cita}/cancelar', [AgendaController::class, 'cancelar'])->name('agenda.cancelar');
    Route::get('agenda/profesionales', [AgendaController::class, 'profesionales'])->name('agenda.profesionales');

    // Configuracion: umbrales internos y parametros por confirmar.
    Route::get('configuracion', [ConfiguracionController::class, 'show'])->name('configuracion.show');
    Route::patch('configuracion', [ConfiguracionController::class, 'actualizar'])->name('configuracion.actualizar');
    Route::post('configuracion/parametros/{clave}', [ConfiguracionController::class, 'confirmarParametro'])->name('configuracion.parametros');
});