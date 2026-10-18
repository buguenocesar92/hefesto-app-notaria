<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoAntecedente;
use App\Enums\EstadoCita;
use App\Enums\EstadoDocumento;
use App\Enums\EstadoExpediente;
use App\Enums\RolUsuario;
use App\Models\AgendaCita;
use App\Models\Antecedente;
use App\Models\ConfiguracionNotaria;
use App\Models\Cobranza;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Etapa;
use App\Models\EtapaTipoOperacion;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\Notaria;
use App\Models\Parte;
use App\Models\Profesional;
use App\Models\TipoOperacion;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de desarrollo: DOS notarias de ejemplo.
 *
 * Se crean dos y no una a proposito: con una sola no se puede demostrar nada
 * del aislamiento. Tener dos en la base de desarrollo permite comprobar a mano
 * que la primera no ve nada de la segunda.
 *
 * QUE HAY QUE MIRAR PRIMERO
 * -------------------------
 * La Notaria de Ejemplo Palma es la que tiene el expediente parado.
 * Abriela y mira el tablero: esa es la pantalla que este producto viene a
 * resolver.
 *
 * SOBRE LA RLS
 * -------------
 * Las tablas de negocio tienen Row Level Security, asi que insertar filas
 * desde un seeder "a pelo" falla: `public.app_tenant_id()` no encuentra
 * contexto y lanza excepcion. Por eso TODO lo de este seeder corre dentro de
 * `ContextoTenant::ejecutarAislado()`. No es un rodeo: es la misma via que usa
 * la aplicacion de verdad. La tabla `notarias` si se puede escribir
 * directamente, porque no lleva RLS.
 *
 * ADVERTENCIA
 * -----------
 * Los usuarios de ejemplo tienen `authentik_sub` ficticios: NO pueden entrar
 * hasta que sus identidades existan en Authentik con ese mismo `sub`, o hasta
 * que se borre el seeder. Ver docs/04, seccion "Datos de desarrollo".
 *
 * Y MAS IMPORTANTE: los tipos de operacion, las etapas y sus umbrales que van
 * abajo son EJEMPLOS INVENTADOS PARA PROBAR LA INTERFAZ. No son el
 * procedimiento de ninguna operacion notarial real, y una notaria no debe
 * copiarlos: cada una define los suyos. Ver docs/06.
 */
class NotariaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $contexto = new ContextoTenant();

        foreach ($this->datosDeEjemplo() as $datos) {
            $notaria = Notaria::create([
                'slug' => $datos['slug'],
                'nombre' => $datos['nombre'],
                'rut' => $datos['rut'],
                'comuna' => $datos['comuna'],
                'direccion' => $datos['direccion'],
                'correo_contacto' => 'contacto@' . $datos['dominio'],
                'telefono_contacto' => '+56 2 2' . substr(hash('crc32b', $datos['slug']), 0, 3) . ' 0000',
                'activo' => true,
            ]);

            $contexto->ejecutarAislado($notaria->id, function () use ($notaria, $datos): void {
                $this->cargarNotaria($notaria, $datos);
            });

            $this->command?->info(sprintf('  Notaria "%s" creada con slug "%s".', $notaria->nombre, $notaria->slug));
        }
    }

    /** @param array<string, string> $datos */
    private function cargarNotaria(Notaria $notaria, array $datos): void
    {
        $this->cargarConfiguracion($notaria);

        $administrador = Usuario::create([
            'tenant_id' => $notaria->id,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'admin@' . $datos['dominio'],
            'nombre' => 'Ana',
            'apellidos' => 'Perez',
            'telefono' => '+56 9 1111 2222',
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $notariaUsuario = Usuario::create([
            'tenant_id' => $notaria->id,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'notario@' . $datos['dominio'],
            'nombre' => 'Rodrigo',
            'apellidos' => 'Vega',
            'telefono' => '+56 9 3333 4444',
            'rol' => RolUsuario::Notario,
            'activo' => true,
        ]);

        $protocolista = Usuario::create([
            'tenant_id' => $notaria->id,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'protocolo@' . $datos['dominio'],
            'nombre' => 'Carla',
            'apellidos' => 'Munoz',
            'telefono' => '+56 9 5555 6666',
            'rol' => RolUsuario::Protocolo,
            'activo' => true,
        ]);

        $auxiliar = Usuario::create([
            'tenant_id' => $notaria->id,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'auxiliar@' . $datos['dominio'],
            'nombre' => 'Luis',
            'apellidos' => 'Torres',
            'telefono' => '+56 9 7777 8888',
            'rol' => RolUsuario::Auxiliar,
            'activo' => true,
        ]);

        $notario = Profesional::create([
            'tenant_id' => $notaria->id,
            'usuario_id' => $notariaUsuario->id,
            'nombre' => 'Rodrigo',
            'apellidos' => 'Vega',
            'cargo' => 'notario',
            'correo' => 'notario@' . $datos['dominio'],
            'duracion_atencion_minutos' => 60,
            'activo' => true,
        ]);

        $protocolistaProfesional = Profesional::create([
            'tenant_id' => $notaria->id,
            'usuario_id' => $protocolista->id,
            'nombre' => 'Carla',
            'apellidos' => 'Munoz',
            'cargo' => 'protocolista',
            'correo' => 'protocolo@' . $datos['dominio'],
            'duracion_atencion_minutos' => 45,
            'activo' => true,
        ]);

        // Tipo de operacion de EJEMPLO, con etapas de ejemplo. Los nombres y
        // los umbrales los define cada notaria; estos solo existen para que
        // haya algo en la pantalla.
        $compraventa = TipoOperacion::create([
            'tenant_id' => $notaria->id,
            'codigo' => 'EJ-COMP',
            'nombre' => 'Ejemplo: compraventa',
            'descripcion' => 'Tipo de operacion inventado para probar la interfaz. '
                . 'Una notaria real define sus propios tipos y sus propias etapas.',
            'color' => '#2563eb',
            'duracion_atencion_minutos' => 60,
            'dias_alerta_por_defecto' => null,
            'dias_critico_por_defecto' => null,
            'activo' => true,
        ]);

        $etapas = [
            [1, 'Recepcion de antecedentes', false],
            [2, 'Revision de documentos', true],
            [3, 'Elaboración', false],
            [4, 'Firma', false],
            [5, 'Cierre y archivo', false],
        ];

        foreach ($etapas as [$orden, $nombre, $requiereAntecedente]) {
            EtapaTipoOperacion::create([
                'tenant_id' => $notaria->id,
                'tipo_operacion_id' => $compraventa->id,
                'orden' => $orden,
                'nombre' => $nombre,
                'descripcion' => null,
                'requiere_antecedente' => $requiereAntecedente,
                // Sin umbral en la plantilla: el semaforo no marca nada
                // hasta que la notaria lo defina. Ver SemaforoEtapa.
                'dias_alerta' => null,
                'dias_critico' => null,
            ]);
        }

        // Un tipo de operacion con umbral YA configurado, para poder ver el
        // semaforo funcionando de inmediato.
        $testamento = TipoOperacion::create([
            'tenant_id' => $notaria->id,
            'codigo' => 'EJ-TEST',
            'nombre' => 'Ejemplo: testamento',
            'descripcion' => 'Otro tipo inventado, este con umbral ya configurado para probar el semaforo.',
            'color' => '#059669',
            'duracion_atencion_minutos' => 45,
            'dias_alerta_por_defecto' => 7,
            'dias_critico_por_defecto' => 21,
            'activo' => true,
        ]);

        foreach ([
            [1, 'Entrevista', false],
            [2, 'Revision de antecedentes', true],
            [3, 'Redaccion', false],
            [4, 'Firma', false],
        ] as [$orden, $nombre, $requiereAntecedente]) {
            EtapaTipoOperacion::create([
                'tenant_id' => $notaria->id,
                'tipo_operacion_id' => $testamento->id,
                'orden' => $orden,
                'nombre' => $nombre,
                'requiere_antecedente' => $requiereAntecedente,
                'dias_alerta' => null,
                'dias_critico' => null,
            ]);
        }

        $this->cargarExpedientes($notaria, $compraventa, $testamento, $notario, $protocolistaProfesional, $administrador, $protocolista);

        $this->command?->info(sprintf(
            '    4 usuarios, 2 profesionales, 2 tipos de operacion, 3 expedientes y documentos para "%s".',
            $notaria->slug
        ));
    }

    private function cargarConfiguracion(Notaria $notaria): void
    {
        ConfiguracionNotaria::create([
            'tenant_id' => $notaria->id,
            'zona_horaria' => 'America/Santiago',

            // SIN UMBRAL. A proposito. Con esto, el semaforo NO marca nada y
            // el tablero muestra el aviso pidiendo configurar el umbral. Es
            // el comportamiento honesto: preferimos no marcar antes que
            // inventar un numero.
            'dias_alerta_por_defecto' => null,
            'dias_critico_por_defecto' => null,

            'aviso_umbral_no_configurado' => 'Esta notaria todavia no configuro cuantos dias espera '
                . 'antes de alarmarse. El semaforo no marca nada porque no hay ningun umbral '
                . 'que comparar. Configurelo en Configuracion. No es un plazo legal.',

            // Los parametros que un notario debe confirmar. SOLO las claves,
            // con `valor` en null: sembrar un valor seria inventar un plazo.
            'parametros_por_confirmar' => [
                'dias_espera_antecedente_no_respondido' => [
                    'etiqueta' => 'Dias que la notaria espera un antecedente antes de alarmarse',
                    'valor' => null,
                    'fundamento' => null,
                    'confirmado_por' => null,
                    'confirmado_en' => null,
                ],
                'dias_revision_documentacion' => [
                    'etiqueta' => 'Dias que la notaria tarda en revisar la documentacion',
                    'valor' => null,
                    'fundamento' => null,
                    'confirmado_por' => null,
                    'confirmado_en' => null,
                ],
                'dias_para_citar_partes' => [
                    'etiqueta' => 'Dias que la notaria espera entre una citacion y la firma',
                    'valor' => null,
                    'fundamento' => null,
                    'confirmado_por' => null,
                    'confirmado_en' => null,
                ],
            ],
        ]);
    }

    private function cargarExpedientes(
        Notaria $notaria,
        TipoOperacion $compraventa,
        TipoOperacion $testamento,
        Profesional $notario,
        Profesional $protocolista,
        Usuario $administrador,
        Usuario $protocolistaUsuario,
    ): void {
        // ------------------------------------------------------------------
        // Expediente 1: EL QUE ESTA ATRASADO. Es el que el producto viene a
        // resolver: la etapa lleva 18 dias esperando un antecedente que
        // nadie fue a buscar.
        // ------------------------------------------------------------------
        $atrasado = Expediente::create([
            'tenant_id' => $notaria->id,
            'folio' => 'EXP-00001',
            // Numero de protocolo inventado SOLO para el ejemplo. En la vida
            // real lo escribe la notaria.
            'numero_protocolo' => '4.512/2026',
            'tipo_operacion_id' => $compraventa->id,
            'profesional_responsable_id' => $notario->id,
            'usuario_responsable_id' => $administrador->id,
            'estado' => EstadoExpediente::EnTramite,
            'descripcion' => 'Compraventa en la comuna de Las Condes. El cliente pregunta seguido.',
            'fecha_inicio' => now()->subDays(24)->toDateString(),
            'dias_alerta_por_defecto' => 7,
            'dias_critico_por_defecto' => 14,
        ]);

        $etapasAtrasado = [
            [1, 'Recepcion de antecedentes', 'terminada', 24, 23, false],
            [2, 'Revision de documentos', 'en_curso', 18, null, true],
            [3, 'Elaboración', 'pendiente', null, null, false],
            [4, 'Firma', 'pendiente', null, null, false],
            [5, 'Cierre y archivo', 'pendiente', null, null, false],
        ];

        foreach ($etapasAtrasado as [$orden, $nombre, $estado, $inicio, $fin, $requiereAntecedente]) {
            Etapa::create([
                'tenant_id' => $notaria->id,
                'expediente_id' => $atrasado->id,
                'orden' => $orden,
                'nombre' => $nombre,
                'estado' => $estado,
                'requiere_antecedente' => $requiereAntecedente,
                'dias_alerta' => 7,
                'dias_critico' => 14,
                'iniciada_en' => $inicio !== null ? now()->subDays($inicio) : null,
                'terminada_en' => $fin !== null ? now()->subDays($fin) : null,
            ]);
        }

        // El antecedente que nadie fue a buscar. Este es el problema real.
        Antecedente::create([
            'tenant_id' => $notaria->id,
            'expediente_id' => $atrasado->id,
            'etapa_id' => $atrasado->etapas()->where('orden', 2)->first()?->id,
            'descripcion' => 'Certificado de vigencia de la compradora',
            'solicitado_a' => 'Registro Civil de Las Condes',
            'estado' => EstadoAntecedente::Pendiente,
            'responsable_id' => $protocolistaUsuario->id,
            'solicitado_en' => now()->subDays(18)->toDateString(),
        ]);

        $this->cargarPartes($notaria, $atrasado, [
            // La MISMA persona aparece con dos roles distintos en el mismo
            // expediente: es exactamente el caso por el que el rol vive en
            // `expediente_partes` y no en `partes`.
            ['Maria', 'Gonzalez', '11.111.111-1', 'maria.gonzalez@ejemplo.cl', 'compradora', true],
            ['Jorge', 'Gonzalez', '12.222.222-2', 'jorge.gonzalez@ejemplo.cl', 'vendedor', false],
            ['Maria', 'Gonzalez', '11.111.111-1', 'maria.gonzalez@ejemplo.cl', 'testigo', false],
        ]);

        $this->cargarDocumentos($notaria, $atrasado);

        Cobranza::create([
            'tenant_id' => $notaria->id,
            'expediente_id' => $atrasado->id,
            'concepto' => 'Anticipo de honorarios (registrado a mano)',
            'monto' => 150000,
            'moneda' => 'CLP',
            'fecha_emision' => now()->subDays(20)->toDateString(),
            'estado' => 'registrada',
            'referencia_documento_tributario' => 'B-1042',
            'observacion' => 'Lo emitio la notaria por su cuenta. Esta aplicacion no emite '
                . 'documentos tributarios electronicos.',
            'registrado_por' => $administrador->id,
        ]);

        // ------------------------------------------------------------------
        // Expediente 2: avanza bien. Para que el tablero tambien tenga algo
        // en verde y no parezca que todo esta mal.
        // ------------------------------------------------------------------
        $avanzando = Expediente::create([
            'tenant_id' => $notaria->id,
            'folio' => 'EXP-00002',
            'numero_protocolo' => '3.988/2026',
            'tipo_operacion_id' => $testamento->id,
            'profesional_responsable_id' => $protocolista->id,
            'usuario_responsable_id' => $protocolistaUsuario->id,
            'estado' => EstadoExpediente::EnTramite,
            'descripcion' => 'Testamento. Va al dia.',
            'fecha_inicio' => now()->subDays(6)->toDateString(),
            'dias_alerta_por_defecto' => 7,
            'dias_critico_por_defecto' => 21,
        ]);

        foreach ([
            [1, 'Entrevista', 'terminada', 6, 5],
            [2, 'Revision de antecedentes', 'en_curso', 5, null],
            [3, 'Redaccion', 'pendiente', null, null],
            [4, 'Firma', 'pendiente', null, null],
        ] as [$orden, $nombre, $estado, $inicio, $fin]) {
            Etapa::create([
                'tenant_id' => $notaria->id,
                'expediente_id' => $avanzando->id,
                'orden' => $orden,
                'nombre' => $nombre,
                'estado' => $estado,
                'requiere_antecedente' => $orden === 2,
                'dias_alerta' => 7,
                'dias_critico' => 21,
                'iniciada_en' => $inicio !== null ? now()->subDays($inicio) : null,
                'terminada_en' => $fin !== null ? now()->subDays($fin) : null,
            ]);
        }

        $this->cargarPartes($notaria, $avanzando, [
            ['Patricia', 'Silva', '13.333.333-3', 'patricia.silva@ejemplo.cl', 'otorgante', true],
            ['Diego', 'Rojas', '14.444.444-4', 'diego.rojas@ejemplo.cl', 'testigo', false],
        ]);

        // ------------------------------------------------------------------
        // Expediente 3: ya cerrado, con etapas terminadas hace rato. El
        // semaforo NO lo marca: una etapa terminada hace un mes no es un
        // atraso, es un atraso que la notaria ya resolvio.
        // ------------------------------------------------------------------
        $cerrado = Expediente::create([
            'tenant_id' => $notaria->id,
            'folio' => 'EXP-00003',
            'numero_protocolo' => '3.401/2026',
            'tipo_operacion_id' => $compraventa->id,
            'profesional_responsable_id' => $notario->id,
            'usuario_responsable_id' => $administrador->id,
            'estado' => EstadoExpediente::Cerrado,
            'descripcion' => 'Cerrado hace un mes.',
            'fecha_inicio' => now()->subDays(70)->toDateString(),
            'fecha_cierre' => now()->subDays(40)->toDateString(),
            'dias_alerta_por_defecto' => 7,
            'dias_critico_por_defecto' => 14,
        ]);

        foreach ([
            [1, 'Recepcion de antecedentes', 70, 68],
            [2, 'Revision de documentos', 68, 60],
            [3, 'Elaboracion', 60, 50],
            [4, 'Firma', 50, 45],
            [5, 'Cierre y archivo', 45, 40],
        ] as [$orden, $nombre, $inicio, $fin]) {
            Etapa::create([
                'tenant_id' => $notaria->id,
                'expediente_id' => $cerrado->id,
                'orden' => $orden,
                'nombre' => $nombre,
                'estado' => 'terminada',
                'requiere_antecedente' => false,
                'dias_alerta' => 7,
                'dias_critico' => 14,
                'iniciada_en' => now()->subDays($inicio),
                'terminada_en' => now()->subDays($fin),
            ]);
        }

        // Agenda: una atencion y un bloqueo.
        AgendaCita::create([
            'tenant_id' => $notaria->id,
            'profesional_id' => $notario->id,
            'expediente_id' => $atrasado->id,
            'tipo_operacion_id' => $compraventa->id,
            'titulo' => 'Revision con la compradora',
            'estado' => EstadoCita::Confirmada,
            'inicio' => now()->addDays(2)->setTime(10, 0),
            'fin' => now()->addDays(2)->setTime(11, 0),
            'duracion_minutos' => 60,
            'creado_por' => $administrador->id,
        ]);

        AgendaCita::create([
            'tenant_id' => $notaria->id,
            'profesional_id' => $notario->id,
            'titulo' => 'Reunion interna',
            'estado' => EstadoCita::Bloqueo,
            'inicio' => now()->addDays(3)->setTime(9, 0),
            'fin' => now()->addDays(3)->setTime(10, 0),
            'duracion_minutos' => 60,
            'bloqueo' => 'reunion interna',
            'creado_por' => $administrador->id,
        ]);
    }

    /** @param list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: string, 5: bool}> $partes */
    private function cargarPartes(Notaria $notaria, Expediente $expediente, array $partes): void
    {
        foreach ($partes as [$nombre, $apellidos, $rut, $correo, $rol, $esPrincipal]) {
            // La persona se crea UNA vez por nombre y RUT: es la misma
            // persona, aunque aparezca con dos roles.
            $parte = Parte::firstOrCreate(
                ['tenant_id' => $notaria->id, 'rut' => $rut],
                [
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'correo' => $correo,
                ]
            );

            // El rol se guarda en la RELACION con el expediente, no en la
            // persona. Maria Gonzalez es compradora y testigo EN ESTE
            // expediente, y es la misma ficha de persona.
            ExpedienteParte::firstOrCreate(
                [
                    'expediente_id' => $expediente->id,
                    'parte_id' => $parte->id,
                    'rol' => $rol,
                ],
                [
                    'tenant_id' => $notaria->id,
                    'es_principal' => $esPrincipal,
                ]
            );
        }
    }

    private function cargarDocumentos(Notaria $notaria, Expediente $expediente): void
    {
        // Un documento con DOS versiones. La primera dejo de estar vigente,
        // pero NO se borro: sigue descargable.
        $contrato = Documento::create([
            'tenant_id' => $notaria->id,
            'expediente_id' => $expediente->id,
            'nombre' => 'Contrato de compraventa',
            'tipo' => 'contrato',
            'estado' => EstadoDocumento::Vigente,
            'version_actual' => 2,
            'fecha_documento' => now()->subDays(20)->toDateString(),
        ]);

        DocumentoVersion::create([
            'tenant_id' => $notaria->id,
            'documento_id' => $contrato->id,
            'version' => 1,
            'fecha_documento' => now()->subDays(23)->toDateString(),
            'ruta_archivo' => 'ejemplo/' . $notaria->slug . '/contrato-v1.pdf',
            'nombre_archivo' => 'contrato-compraventa-v1.pdf',
            'mime' => 'application/pdf',
            'tamano_bytes' => 184320,
            'hash_sha256' => hash('sha256', $notaria->slug . '-contrato-v1'),
            'vigente_hasta' => now()->subDays(20),
            'observacion' => 'Primera version, corregida despues.',
        ]);

        DocumentoVersion::create([
            'tenant_id' => $notaria->id,
            'documento_id' => $contrato->id,
            'version' => 2,
            'fecha_documento' => now()->subDays(20)->toDateString(),
            'ruta_archivo' => 'ejemplo/' . $notaria->slug . '/contrato-v2.pdf',
            'nombre_archivo' => 'contrato-compraventa-v2.pdf',
            'mime' => 'application/pdf',
            'tamano_bytes' => 191440,
            'hash_sha256' => hash('sha256', $notaria->slug . '-contrato-v2'),
            'vigente_hasta' => null,
        ]);

        // Un documento marcado como reemplazado: queda en el expediente.
        $plano = Documento::create([
            'tenant_id' => $notaria->id,
            'expediente_id' => $expediente->id,
            'nombre' => 'Plano del inmueble (version anterior)',
            'tipo' => 'plano',
            'estado' => EstadoDocumento::Reemplazado,
            'version_actual' => 1,
            'fecha_documento' => now()->subDays(22)->toDateString(),
            'reemplazado_en' => now()->subDays(15),
            'observacion' => 'Reemplazado por el plano actualizado. NO se borro.',
        ]);

        DocumentoVersion::create([
            'tenant_id' => $notaria->id,
            'documento_id' => $plano->id,
            'version' => 1,
            'fecha_documento' => now()->subDays(22)->toDateString(),
            'ruta_archivo' => 'ejemplo/' . $notaria->slug . '/plano-v1.pdf',
            'nombre_archivo' => 'plano-inmueble-v1.pdf',
            'mime' => 'application/pdf',
            'tamano_bytes' => 96256,
            'hash_sha256' => hash('sha256', $notaria->slug . '-plano-v1'),
            'vigente_hasta' => now()->subDays(15),
        ]);
    }

    /** @return list<array<string, string>> */
    private function datosDeEjemplo(): array
    {
        return [
            [
                'slug' => 'notaria-consul-template-palma',
                'nombre' => 'Notaria de Ejemplo Palma',
                'rut' => '52.111.111-9',
                'comuna' => 'Las Condes',
                'direccion' => 'Av. Ejemplo 1234, of. 501',
                'dominio' => 'notariapalma.cl',
            ],
            [
                'slug' => 'notaria-de-ejemplo-sur',
                'nombre' => 'Notaria de Ejemplo Sur',
                'rut' => '52.222.222-8',
                'comuna' => 'Providencia',
                'direccion' => 'Calle Ejemplo 5678',
                'dominio' => 'notariasur.cl',
            ],
        ];
    }
}