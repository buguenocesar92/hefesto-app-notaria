<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista publica de notarias: la excepcion consciente de la RLS.
 *
 * `notarias` NO lleva Row Level Security, porque se consulta antes del login,
 * cuando todavia no hay sesion y por lo tanto no hay `app.tenant_id` que
 * poner. Es exactamente el punto ciego documentado en
 * `apps/portal-autoservicio/docs/03-MULTITENANCY.md`, seccion 4, y se
 * resuelve igual.
 *
 * Antes del login la aplicacion NO lee `notarias` crudo: lee esta vista, que
 * expone TRES columnas (`slug`, `nombre`, `activo`) y solo de las notarias
 * activas. No expone RUT, direccion, correo ni telefono.
 *
 * El comando `php artisan notaria:verificar-aislamiento` comprueba que la
 * vista siga teniendo exactamente esas tres columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_notarias_publicas AS
            SELECT
                slug,
                nombre,
                activo
            FROM public.notarias
            WHERE activo = true
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON VIEW public.vw_notarias_publicas IS
            'Lo unico que se puede leer de notarias antes del login: slug, nombre y si esta activa. No expone datos de contacto.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS public.vw_notarias_publicas');
    }
};