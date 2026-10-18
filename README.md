# Gestión de Notarias

Aplicación propia de **Hefesto Studio** para notarias de Chile: agenda por
notario, expedientes, etapas del tramite, documentos, partes y registro de
cobranzas.

Software original escrito por Hefesto. No es un kit de despliegue de ninguna
herramienta de terceros.

---

## LEER ANTES QUE NADA

> **ESTO NO ESTA LISTO PARA UNA NOTARIA REAL.**
>
> Una notaria es una oficina del Estado. Responde al Estado, y un error de
> registro tiene consecuencias que ninguna aplicación cubre: no hay una
> segunda copia del hecho. Un sistema puede dejar de funcionar y nadie se
> entera; un sistema que registra mal, en cambio, deja una cosa mal
> asentada para siempre.
>
> Esta aplicación esta en estado **"código escrito, sin ejecutar"**. No se ha
> corrido en ningun entorno real. Ver [docs/10](docs/10-PRUEBAS-Y-ESTADO.md),
> que separa lo probado de lo no probado sin adornos.

### La restriccion que define todo el proyecto

**Esta aplicación no reemplaza ni interpreta la ley, y no puede.**

No es una limitacion de la versión: es una limitacion de naturaleza. Un
programa no sabe que es un testamento, ni que documentos exige una
operación, ni cuanto tiempo tiene el interestedo para concurrir. Por eso:

- **No hay ningun plazo legal en el código.** Ni en la base, ni en un
  archivo de configuración por omision.
- **No hay ningun valor legal de ejemplo.** Donde un dato deberia existir y
  esta aplicación no lo sabe, el campo queda **vacio** y se documenta que un
  notario tiene que cargarlo, con su fundamento.
- **Lo que el sistema hace es gestionar y registrar lo que ya existe.** No
  dice que un tramite sea valido, ni que un documento sirva, ni que un plazo
  se haya vencido.

Donde hay un dato que el sistema no puede saber, esta **configurable** y la
pantalla lo muestra vacio, diciendo que hay que confirmarlo. Ver
[docs/05](docs/05-CONFIGURACION.md).

---

## El producto: el semaforo de tramites atrasados

Lo que realmente consume tiempo en una notaria no es escribir. Es el
**seguimiento**: un expediente parado tres semanas por un antecedente que
nadie fue a buscar, mientras el cliente llama a preguntar.

Por eso el centro del sistema es el expediente, y sobre el se cuenta una sola
cosa:

> **Cuantos dias lleva cada etapa en su estado actual, comparado contra el
> umbral que la notaria configuro.**

Si lo supera, la etapa aparece en amarillo. Si supera el segundo umbral, en
rojo. Solo.

**Lo que el semaforo es:** una alarma interna, con los dias que la notaria
eligio.

**Lo que el semaforo no es:** un plazo legal, un vencimiento, ni una prediccion
de cuando el tramite deberia estar listo.

### La regla que mas importa de todo el sistema

**Si la notaria no configuro el umbral, no se marca nada.**

Una etapa puede llevar trescientos dias esperando y el sistema no la marca,
porque no hay ningun número con que compararla. El tablero muestra el aviso
pidiendo configurar el umbral.

El motivo es concreto: un "rojo" con un umbral inventado le diria a una
notaria "esto se vencio", y alguien moveria plazos de personas reales en
función de un número que salio de la nada. Un tablero con huecos se nota y se
corrige. Un tablero con números falsos se usa.

Ver [docs/01](docs/01-ARQUITECTURA.md), seccion "El semaforo".

---

## Modulos

| Modulo | Que resuelve |
|---|---|
| **Agenda del notario** | Citas y bloqueos, por profesional y por tipo de operación, con duracion. |
| **Expedientes** | Una operación en curso, con su tipo, sus partes, sus antecedentes y su estado. **El centro del sistema.** |
| **Etapas del tramite** | Los pasos que definio la notaria, con fecha de inicio y de termino. **Aquí vive el semaforo.** |
| **Antecedentes** | Lo que hay que conseguir, a quien se le pidio y cuando. El modulo que ataca el problema real. |
| **Documentos** | Con versión y fecha. **Nunca se borran: se reemplazan.** |
| **Partes** | Las personas de cada operación. **El rol es de la relacion, no de la persona.** |
| **Facturacion** | Registro de lo facturado. **No emite documentos tributarios electronicos**, y no habra código que lo haga. |

---

## Documentos: por que no hay un boton de borrar

Un expediente notarial sin su historial documental no vale nada.

Por eso, en todo el sistema:

- **No hay ruta `DELETE` de documentos.** No existe `destroy()` en el
  controlador, ni metodo `borrar` en el servicio.
- **No hay ruta `DELETE` de expedientes.** Uno mal abierto se **anula**.
- Subir una versión nueva **no borra** la anterior: la marca como no vigente,
  con fecha, y sigue descargable.

Dos pruebas vigilan que esas rutas no aparezcan. Ver
[docs/09](docs/09-API.md).

---

## Facturacion electrónica: lo que no se hace

Hefesto tiene el kit **LibreDTE**, pero su versión publicada es una **beta** y
su API es de **solo lectura**: no permite automatizar la emision.

Por eso en esta aplicación **no hay, y no habra, código que emita, timbre,
anule ni envie un documento tributario electrónico**.

Lo que hay es un **registro**: la notaria escribe a mano lo que ya emitio por
su cuenta, con su número real. Es un respaldo contable, no un emisor.

Ver [docs/05](docs/05-CONFIGURACION.md), seccion "Facturacion electrónica".

---

## Datos personales

Los datos de las partes y de las operaciones sobre patrimonio, familia y
sucesiones estan protegidos por la **Ley 19.628**.

- Aislamiento por notaria en la **base de datos** (Row Level Security), no
  solo en el código.
- Permisos por rol dentro de la notaria.
- **Auditoria de acceso** a expedientes.
- Bitacora en modo solo agregar.

Ver [docs/07](docs/07-SEGURIDAD-Y-LEY-19628.md).

---

## Aislamiento entre notarias

Columna `tenant_id` mas **Row Level Security** de PostgreSQL, con `WITH CHECK`,
`FORCE ROW LEVEL SECURITY` y el contexto viajando con `set_config(..., true)`,
de alcance de transacción.

Es el patron ya decidido y documentado en
`apps/portal-autoservicio/docs/03-MULTITENANCY.md`, reutilizado tal cual, sin
inventar un cuarto enfoque.

Ver [docs/03](docs/03-MULTITENANCY-Y-ROLES.md).

---

## Estado: código escrito, sin ejecutar

En el entorno donde se escribio esto **no habia PHP, ni Composer, ni Docker**.
No se corrio ni una linea.

| Que | Estado |
|---|---|
| Código de la API | escrito, **sin ejecutar** |
| Código de la interfaz | escrito, **sin ejecutar** |
| Migraciones | escritas, **sin ejecutar** |
| Pruebas | escritas, **sin ejecutar** |
| Aislamiento entre notarias | **no verificado en una base real** |

Ver [docs/10](docs/10-PRUEBAS-Y-ESTADO.md) para el detalle honesto, sin
adornos.

---

## Estructura

```
apps/notaria/
├── README.md
├── .env.example
├── docs/       # arquitectura, datos, multitenancy, instalacion,
│               # configuracion, primer expediente, seguridad, respaldos,
│               # API, pruebas y estado
├── api/        # Laravel + PostgreSQL (RLS)
├── web/        # Nuxt (SSR)
└── deploy/     # Docker Compose con versiones fijadas
```

## Documentacion

| Documento | De que trata |
|---|---|
| [01 Arquitectura](docs/01-ARQUITECTURA.md) | Las piezas, y sobre todo el semaforo |
| [02 Modelo de datos](docs/02-MODELO-DE-DATOS.md) | Las tablas y por que son como son |
| [03 Multitenancy y roles](docs/03-MULTITENANCY-Y-ROLES.md) | Aislamiento en la base, y quien puede que |
| [04 Instalación](docs/04-INSTALACION.md) | Como se levanta |
| [05 Configuración](docs/05-CONFIGURACION.md) | Los parámetros configurables, y la facturacion electrónica |
| [06 Primer expediente](docs/06-PRIMER-EXPEDIENTE.md) | Un expediente de punta a punta |
| [07 Seguridad y Ley 19.628](docs/07-SEGURIDAD-Y-LEY-19628.md) | Datos personales y auditoria |
| [08 Respaldos](docs/08-RESPALDOS.md) | Respaldo, y por que los documentos importan |
| [09 API](docs/09-API.md) | Endpoints, y las rutas que no existen |
| [10 Pruebas y estado](docs/10-PRUEBAS-Y-ESTADO.md) | Lo probado, lo no probado, y los riesgos |

---

**Hefesto Studio**
Software con criterio. Y con las advertencias escritas antes que los
elogios.