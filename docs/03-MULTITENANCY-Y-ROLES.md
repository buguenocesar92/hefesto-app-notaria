# 03 - Multitenancy y roles

## 1. Por que esto es lo mas importante del proyecto

En el portal de una PyME, que un cliente vea los datos de otro es un problema
comercial. En una notaria es otra cosa:

- Son datos de **patrimonio, familia y sucesiones** de personas identificables.
- Estan protegidos por la **Ley 19.628**.
- Una fuga **no se corrige con un disculpo**.

Por eso el aislamiento **no puede depender solo del código de la
aplicación**.

---

## 2. El patron: columna `tenant_id` mas RLS

Es el patron ya decidido y documentado en
`apps/portal-autoservicio/docs/03-MULTITENANCY.md`, **reutilizado tal cual**.
No es un cuarto enfoque, ni una variante, ni una mejora.

Todas las tablas de negocio llevan `tenant_id`, y PostgreSQL aplica una
política de **Row Level Security** que exige que `tenant_id` sea igual al de
la peticion.

| Capa | Que hace | Que detecta |
|---|---|---|
| Aplicación (Policies y middleware) | Decide y filtra | Errores de lógica, consultas mal escritas |
| Base de datos (RLS) | Impide que PostgreSQL devuelva filas ajenas | **Todo lo que se le escape a la capa de arriba** |

La base es la última linea. Si la aplicación tiene un error, la base sigue sin
entregar datos de otra notaria.

Implementado en
`api/database/migrations/2026_10_17_000021_activar_row_level_security.php`.

---

## 3. El mecanismo

### 3.1 La función de contexto

```sql
CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid ...
    valor := current_setting('app.tenant_id', true);

    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION USING ERRCODE = 'insufficient_privilege', ...
    RETURN valor::uuid;
```

La función **lanza excepcion** si no hay contexto. Podria haber devuelto `NULL`
y filtrado todo en silencio, pero se eligio que falle de forma ruidosa:

- Si devuelve `NULL`, la consulta devuelve cero filas **sin aviso**. Alguien lo
  "arregla" devolviendo `NULL` cuando hace falta (un reporte, una consola) y
  la puerta queda abierta sin que nada lo delate.
- Si lanza excepcion, el error aparece en el log **en el momento**, y se sabe
  exactamente que operación se escapo del middleware.

Es el criterio de un candado: importa que falle **cerrado**.

### 3.2 La política

Para cada tabla con datos de negocio:

```sql
ALTER TABLE public.expedientes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.expedientes FORCE ROW LEVEL SECURITY;

CREATE POLICY politica_aislamiento_expedientes ON public.expedientes
    FOR ALL
    TO PUBLIC
    USING (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

Tres detalles que importan:

- `USING` filtra lo que se **lee**.
- `WITH CHECK` impide **escribir** una fila con un `tenant_id` distinto. Sin
  esto, un `INSERT` con el tenant equivocado dejaria una fila invisible que
  nadie podria ver ni borrar.
- `FORCE ROW LEVEL SECURITY` hace que la política aplique **también al dueno
  de la tabla**. Sin FORCE, en desarrollo (donde el rol de la aplicación suele
  ser el dueno) el aislamiento seria decorativo justo en el entorno donde se
  prueba.

### 3.3 Como viaja el contexto

```php
DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $tenantId]);
```

El `true` final es lo mas importante del archivo: es **alcance de
transacción**. Al confirmar o revertir, PostgreSQL descarta el valor solo.

Con un `SET` normal (alcance de sesión), el valor sobreviviria a la peticion, y
el **worker de colas**, que reutiliza la misma conexión para trabajos de
notarias distintas, podria leer los expedientes de otra. Ese es exactamente el
fallo que este diseno evita.

Por eso `ContextoTenant::establecer()` **lanza excepcion si no esta dentro de
una transacción**.

### 3.4 Quien fija el contexto

Solo una clase: `App\Services\Tenancy\ContextoTenant`, y solo a traves del
middleware `App\Http\Middleware\ResolverTenant`, que envuelve la peticion
autenticada en una transacción con la notaria ya fijada.

**Tablas protegidas** (17):

`usuarios`, `profesionales`, `configuracion_notaria`, `tipos_operacion`,
`etapas_tipo_operacion`, `expedientes`, `etapas`, `antecedentes`, `partes`,
`expediente_partes`, `documentos`, `documento_versiones`, `agenda_citas`,
`cobranzas`, `alertas_semaforo`, `bitacora_accesos`, `contadores`.

La migración **falla ruidosamente** si alguna de ellas quedo sin columna
`tenant_id`, en vez de dejar una tabla de datos de notaria sin protección.

---

## 4. El punto ciego: `notarias` y `sessions`

`notarias` **no lleva RLS**, a propósito. Se consulta durante el login,
cuando todavia no hay sesión y por lo tanto no hay `app.tenant_id` que poner.

Es una excepcion consciente, con dos compensaciones:

**Compensacion 1: la vista `vw_notarias_publicos`.** Antes del login, la
aplicación no lee `notarias` crudo: lee una vista que expone **tres**
columnas (`slug`, `nombre`, `activo`) y solo de las notarias activas. No
expone RUT, direccion, correo ni telefono.

**Compensacion 2: el rol de la aplicación no es superusuario.** Quien tenga
acceso SQL directo puede leer `notarias` entera. Por eso el despliegue exige
revisar los permisos y `notaria:verificar-aislamiento` comprueba que el rol no
sea superusuario ni tenga `BYPASSRLS`.

`sessions` tiene el mismo tratamiento: no lleva RLS, porque es la sesión de la
capa de sesión y tiene que poder escribirse antes de saber de que notaria
viene la persona.

**Riesgo residual, dicho con todas las letras:** un `slug` se puede enumerar
antes de autenticarse, porque aparece en la URL de entrada. Un atacante puede
descubrir que la Notaria X usa el sistema. No puede ver mas que su nombre.

---

## 5. Verificación: `notaria:verificar-aislamiento`

El aislamiento no es solo el código que escribimos: es el **estado en que
quedo la base**. Un despliegue bien intencionado pero mal configurado deja la
aplicación funcionando y, al mismo tiempo, completamente abierta entre
notarias. Nada en la interfaz lo delata.

Por eso este comando se ejecuta en el despliegue y devuelve código de salida 1
si algo falla. Comprueba:

1. El rol de la base no es superusuario ni tiene `BYPASSRLS`.
2. Las 17 tablas tienen RLS **activada**, **forzada** y **con política**.
3. `public.app_tenant_id()` **falla** sin contexto (falla cerrado).
4. Con una notaria inexistente no se lee ninguna fila, y el contexto **no
   queda colgando** despues de la transacción.
5. La vista pública expone **exactamente** las tres columnas acordadas.

---

## 6. Roles

### El rol no es el grupo de Authentik

El grupo de Authentik (`AUTHENTIK_GRUPO_*`) sirve **unicamente para el primer
alta**. De ahi en adelante manda `usuarios.rol`, que la notaria controla desde
la aplicación.

Y una cuenta **no se autoaprovisiona**: si la identidad de Authentik es
valida pero la persona no esta dada de alta, el acceso se rechaza. Autoaprovisionar
convertiria cualquier cuenta del proveedor de identidad en acceso a
expedientes notariales.

### Que sabe y que no sabe esta aplicación

`Notario` y `Protocolo` son **nombres de cargo**. Esta aplicación **no sabe
que facultades tiene un notario** según la ley aplicable, ni las restringe por
si misma.

Lo que puede hacer cada rol es lo que dice la tabla de abajo, que es una
**decisión de la notaria**. Si el marco aplicable exige otras restricciones,
hay que cambiarlas explicitamente: no confiar en que el nombre del rol las
implica.

### La matriz

| Accion | administrador | notario | protocolo | auxiliar |
|---|:---:|:---:|:---:|:---:|
| Ver expedientes | si | si | si | si |
| Abrir un expediente | si | si | si | si |
| Editar los datos del expediente | si | si | si | - |
| **Mover el estado de una etapa** | si | si | si | - |
| Pedir y registrar antecedentes | si | si | si | - |
| **Cerrar un expediente** | si | si | - | - |
| Anular un expediente | si | si | - | - |
| Subir documentos | si | si | si | - |
| **Marcar un documento como reemplazado** | si | si | - | - |
| Registrar cobros | si | si | - | - |
| Editar una parte | si | si | si | - |
| Ver y agendar citas | si | si | si | si |
| **Configurar los umbrales del semaforo** | si | si | - | - |
| **Confirmar un dato por confirmar** | si | si | - | - |
| Configurar tipos de operación | si | - | - | - |

### Por que el umbral es un dato sensible

Un auxiliar que pudiera cambiar el umbral de 5 a 30 diaseria estar **apagando
alarmas**, sin querer y sin que nadie lo notara. Por eso
`ConfiguracionPolicy::actualizar()` exige a notario o administrador.

### "Cerrar" no es "legalizar"

La accion se llama **cerrar**, y no `legalizar` ni `validar`, a propósito.
Cerrar significa que **la notaria dio el tramite por terminado**. El sistema
no puede declarar que la operación sea valida, y el nombre de la accion no
debe sugerir que si.

### Cuando una Policy rechaza: 404, no 403

Un `403` confirmaria que el recurso existe. Como la RLS ya impide que exista a
ojos de la otra notaria, `404` es ademas la respuesta correcta.

---

## 7. Lo que NO se hizo, y sigue siendo un riesgo

- **No hay revision de seguridad externa.**
- **No hay pruebas de penetracion.**
- **Las pruebas de aislamiento estan escritas pero NO se ejecutaron**: en el
  entorno donde se escribio el código no habia PostgreSQL. Ver
  [10](10-PRUEBAS-Y-ESTADO.md).
- **La política protege el dato, pero no el archivo.** Si se escribiera una
  ruta maliciosa en la base, la RLS no ayuda. Por eso
  `GestorDeDocumentos::rutaAbsolutaSegura()` comprueba que la ruta resuelta
  siga dentro del volumen, y el chequeo se hace **antes** de servir el
  archivo.

---

## 8. Resumen

| | Esquema por notaria (descartado) | Columna + RLS (elegido) |
|---|---|---|
| Migraciones | Una por esquema x N notarias | Una sola |
| Pool de conexiones | Se dimensiona por notaria | Uno solo |
| Aislamiento | En la aplicación (`search_path`) | En la base de datos |
| Error de contexto | Falla en silencio | Falla ruidosamente |
| Riesgo de fuga | Alto (depende de cada consulta) | Bajo (la base lo impide) |

---

## 9. Los dos roles de PostgreSQL

La base de esta aplicación corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre notarías funcione en vez de
quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `notaria_admin` | `POSTGRES_USER` | Crear la base y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `notaria_app` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

Dos nombres que conviene mirar con calma: el rol de aplicación es `notaria_app`,
y el valor por omisión de la base en el `compose` es `notaria_dev`. Ninguna de
las dos cosas es un error de tipeo.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: una
notaría habría visto los expedientes, las escrituras y los documentos de las
demás, sin error y sin aviso. En una notaría eso no es un detalle: es el
aislamiento entero.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE notaria_dev OWNER TO notaria_app;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan notaria:verificar-aislamiento
```

El comando consulta `pg_roles` sobre el rol de la conexión y falla con código 1
si ese rol es superusuario o tiene `BYPASSRLS`, y después revisa que la RLS, las
políticas y el contexto de notaría protejan los datos de cada notaría.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).