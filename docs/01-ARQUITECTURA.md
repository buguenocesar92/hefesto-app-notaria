# 01 - Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

## 1. El problema que resuelve esta aplicación

Una notaria no tiene problemas de papel. Tiene problemas de **seguimiento**.

Un expediente se abre, se le piden antecedentes, y nadie va a buscar lo que
falta. El expediente queda parado. Nadie lo ve porque nadie lo mira: cada
persona ve su propia parte. El cliente llama a preguntar y la respuesta
honesta es "esta en proceso", que no informa nada.

La función de esta aplicación es una sola: **hacer visible, ordenado y sin
discusion que tramites se quedaron esperando**.

Todo lo demas (agenda, documentos, partes, facturacion) existe porque un
expediente no se sostiene solo.

---

## 2. Las piezas

```
    Navegador
        |
        |  (mismo origen, cookie de sesion)
        v
    Caddy  ----------------------------.
        |                                |
        | /api/**                        | todo lo demas
        v                                v
    API (Laravel)                  Web (Nuxt, SSR)
        |                                |
        | set_config('app.tenant_id')    | reenvia /api/**
        v                                |
    PostgreSQL con RLS <----------------'
        |
        v
    Redis (cache y colas)
```

| Pieza | Que hace | Por que esta ahi |
|---|---|---|
| **Nuxt (SSR)** | La interfaz | El servidor renderiza la primera pantalla: la persona ve el tablero sin esperar a que corra JavaScript. |
| **BFF en Nuxt** | `server/api/[...ruta].ts` | El navegador nunca habla directo con la API. |
| **Laravel** | La API | Autorización, reglas de negocio, y donde vive el calculo del semaforo. |
| **PostgreSQL + RLS** | Los datos | El aislamiento entre notarias se aplica **aca**, no en el código. |
| **Redis** | Cache y colas | El despleiegue de la cola es separado del de la API. |
| **Authentik** | Identidad | Las contrasenas nunca pasan por esta aplicación. |

### Por que el navegador no habla directo con la API

La sesión viaja en una cookie `HttpOnly` + `Secure` + `SameSite=Lax`. Con una
cookie así, el navegador solo la envia **a su mismo origen**. Si la API
respondiera en otro origen, habria que resolver CORS con credenciales y el
token CSRF se volveria mas fragil.

Al pasar todo por un origen (Caddy reparte `/api/**` y el resto), la cookie
viaja sola y el CSRF se resuelve con la cabecera `X-XSRF-TOKEN`, que el BFF
copia automáticamente.

Ver `web/server/api/[...ruta].ts`.

---

## 3. EL SEMAFORO

Esta es la parte del sistema que justifica todo lo demas.

### Que se compara

Dos números, y nada mas:

1. Cuantos **dias completos** lleva una etapa en su estado actual.
2. Cuantos dias dijo la **notaria** que espera antes de alarmarse (`N`).

Si (1) supera a (2), la etapa se marca.

### Los niveles

| Nivel | Cuando | Que significa |
|---|---|---|
| `sin_umbral` | La etapa no tiene `dias_alerta` | **No se marca nada.** La notaria todavia no configuro el umbral. |
| `verde` | Dias por debajo de N | Dentro del criterio de la notaria. |
| `amarillo` | Dias iguales o mayores que N | La notaria dijo que a partir de aca conviene mirar. |
| `rojo` | Dias iguales o mayores que M (`dias_critico`) | Conviene mirar mas todavia. |
| `terminado` | La etapa termino o se omite | **No es un atraso**, por mas viejo que sea. |

### La regla que protege a la notaria

**Sin umbral configurado, no se marca nada.** No hay valor por omision.

El motivo esta en `App\Services\Semaforo\SemaforoEtapa`, y es el mismo en
todo el sistema: un rojo con un número inventado le diria a una notaria "esto
se vencio", y alguien moveria plazos de personas reales en función de un
número que salio de la nada.

Por eso el nivel `sin_umbral` existe, y por eso el tablero lo muestra escrito
en vez de esconderlo. Una pantalla con huecos se nota y se corrige. Una
pantalla con números falsos se usa.

### Donde vive el calculo

En **una sola clase**, sin dependencias:

```
api/app/Services/Semaforo/SemaforoEtapa.php
```

Es un objeto de valor: recibe el estado, los umbrales, la fecha de referencia
y el momento de la medicion. No lee la base, no lee el reloj por su cuenta, no
depende de nada.

Esa concentracion es deliberada:

- La interfaz **no recalcula**. Pinta el nivel que le llega. Si el navegador
  tuviera su propia regla de dias, habria dos reglas, y con el tiempo
  dirian cosas distintas sobre la misma etapa.
- Es facil de probar sin base de datos: `tests/Unit/SemaforoEtapaTest.php`.

### De donde sale la fecha de referencia

Se cuenta desde lo que corresponda:

| Situacion | Se cuenta desde |
|---|---|
| La etapa empezo | `etapas.iniciada_en` |
| La etapa sigue pendiente | La fecha de inicio del expediente |
| No hay ninguna de las dos | Cero dias (no se inventa una fecha) |

Una etapa que nunca empezo igual lleva esperando desde que se abrio el
tramite. Y si no hay ni una fecha ni la otra, se cuentan cero dias: antes
cero que inventar.

### Que hace el semaforo con el historial

Un nivel es información de **este momento**, y por si sola no sirve. La
pregunta util es si el problema es de hoy o de hace una semana.

Por eso `alertas_semaforo` guarda la historia:

- La primera vez que una etapa cruza un umbral, se abre una **alerta**.
- Mientras siga en ese nivel, la alerta se **actualiza** (suben los dias).
- Si baja a verde, la alerta se **cierra**.
- Si vuelve a subir, se abre una **nueva**: es un hecho distinto.

Una alerta abierta por etapa y nivel, impuesto por un indice único parcial
en la base, no solo en PHP.

### Como corre

```
php artisan notaria:revisar-semaforo
```

Ademas, la pantalla del tablero corre una pasada fresca al abrirse, para que
el tablero no dependa de cuando corrio el cron.

---

## 4. El expediente es el centro

De un expediente cuelgan las etapas, los antecedentes, las partes, los
documentos y las cobranzas. Si hay que explicar el modelo en una frase, es
eso.

Dos decisiones que salen de esa centralidad:

**El estado del expediente no se escribe, se deriva.**
`MaquinaDeEstadosEtapa::recalcularEstadoExpediente()` lo calcula a partir de
las etapas. Poder marcar un expediente como "cerrado" con etapas abiertas por
el camino es exactamente el tipo de dato que despues nadie sabe defender.

**El número de protocolo lo pone la notaria.**
El sistema genera un `folio` interno, que no sale de la aplicación. El número
de protocolo lo escribe una persona. Un número mal puesto en una notaria es un
problema administrativo serio, y lo peor que un software puede hacer es
inventarlos.

---

## 5. El rol es de la relacion, no de la persona

Maria Gonzalez es compradora en un expediente y testigo en otro. Es la misma
persona.

Si el rol fuera un atributo de la persona, habria que duplicarla, y la
duplicación, con el tiempo, produce expedientes cruzados. Un expediente
cruzado en una notaria es un problema serio.

Por eso:

```
partes               -> quien es la persona (nombre, RUT, contacto)
expediente_partes    -> (expediente, persona, ROL, es_principal)
```

La misma lógica se aplica al profesional responsable y al `solicitado_a` de
los antecedentes: son atributos de la relacion, no de la persona.

---

## 6. Multiples notarias en una instalación

Una instalación puede atender varias notarias. Los datos de cada una estan
aislados por `tenant_id` con **Row Level Security**, que se aplica en la base
y no en el código.

Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## 7. Lo que esta aplicación NO es

Con la misma claridad que el semaforo:

- **No es un sistema legal.** No dice que un tramite sea valido.
- **No conoce plazos legales.** Donde deberia haber uno, hay un campo vacio
  que un notario tiene que cargar.
- **No emite documentos tributarios electronicos.** Ni ahora ni despues: el kit
  disponible tiene su API en solo lectura.
- **No borra documentos ni expedientes.** Se reemplazan o se anulan.
- **No tiene un concepto de "operación correcta".** El estado `cerrado`
  significa que la notaria dio el tramite por terminado. Nada mas.

---

## 8. Mapas de archivos

| Necesito... | Archivo |
|---|---|
| La regla del semaforo | `api/app/Services/Semaforo/SemaforoEtapa.php` |
| Correr el semaforo | `api/app/Services/Semaforo/RevisorDeSemaforo.php` |
| La pantalla del tablero | `api/app/Http/Controllers/TableroController.php` |
| Abrir un expediente | `api/app/Services/Expedientes/AperturadorDeExpediente.php` |
| Mover una etapa | `api/app/Services/Expedientes/MaquinaDeEstadosEtapa.php` |
| La regla de documentos | `api/app/Services/Documentos/GestorDeDocumentos.php` |
| El aislamiento | `api/database/migrations/2026_10_17_000021_activar_row_level_security.php` |
| Verificar el aislamiento | `api/app/Console/Commands/NotariaVerificarAislamientoCommand.php` |