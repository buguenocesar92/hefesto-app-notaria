# 07 - Seguridad y Ley 19.628

## 1. Que datos maneja esto

| Dato | Donde | Sensibilidad |
|---|---|---|
| Datos de las partes: nombre, RUT, contacto, direccion | `partes` | **Alta.** |
| Operaciones sobre patrimonio, familia y sucesiones | `expedientes`, `etapas` | **Alta por la naturaleza misma del tramite**, no por una etiqueta. |
| Documentos de un expediente | `documentos`, `documento_versiones`, volumen | **Muy alta.** Pueden contener datos de terceros que no son partes. |
| Quien entro y a que expediente | `bitacora_accesos` | Media. Es la que sirve para responder reclamos. |

La sensibilidad no viene de una etiqueta que le ponga el sistema: viene de que
una escritura pública testamento informa sobre la salud de una persona, y una
sucesion informa sobre su muerte y sobre la de sus seres queridos. **No hace
falta que los datos sean "sensibles" para que lo sean.**

Todo lo de aca esta sujeto a la **Ley 19.628** sobre protección de la vida
privada.

---

## 2. Que hace el sistema al respecto

| Medida | Donde |
|---|---|
| Aislamiento por notaria en la **base de datos** | [03](03-MULTITENANCY-Y-ROLES.md) |
| Permisos por rol | [03](03-MULTITENANCY-Y-ROLES.md), seccion 6 |
| **Auditoria de acceso** a expedientes | Seccion 3 de este documento |
| Sesión en cookie `HttpOnly` + `Secure` + `SameSite` | `api/.env.example` |
| CSRF | Sesión 4 |
| Validación de entrada | Seccion 5 |
| Autorización en cada endpoint | Seccion 6 |
| **Sin datos de ejemplo reales** | Seccion 7 |
| Respuesta 404 en vez de 403 | Seccion 6 |

---

## 3. Auditoria de acceso

### Por que no es "por cumplir"

En una notaria, la bitacora responde una pregunta que **se hace**: "quien
abrio este expediente y cuando".

Esa pregunta aparece cuando un tercero reclama. La respuesta esta o no esta, y
no se puede reconstruir despues. Por eso `bitacora_accesos` se escribe en
modo **solo agregar**: no hay endpoint que la edite ni que la borre.

### Que se anota

`AuditarAcceso` registra **las peticiones que escriben**, no las que leen:

| Accion | Ruta |
|---|---|
| `crear` | cualquier `POST` |
| `actualizar` | `PATCH`, `PUT` |
| `cambiar_estado` | `.../estado` |
| `registrar_antecedente` | `.../antecedentes` |
| `agregar_version_documento` | `.../versiones` |
| `marcar_reemplazado` | `.../reemplazar` |
| `registrar_cobro` | `.../cobranzas` |
| `actualizar_configuracion` | `.../configuracion` |

Con direccion IP, agente de usuario, ruta y el **identificador del
expediente** cuando la peticion va sobre uno.

### Por que no se anotan las lecturas

Decisión consciente, documentada en el código:

En una notaria todas las personas de la notaria ven los expedientes (es una
decisión operativa: el auxiliar que agenda necesita saber a quien llama).
Anotar cada lectura llenaria la tabla de ruido y la haria inutil.

**Si lo que hace falta es trazar lecturas, hay que decirlo explicitamente y
implementarlo por separado.** No se deduce de un filtro de metodo HTTP.

### Los hechos anteriores al login

Estado invalido, token expirado, intento contra una notaria que no existe:
**no tienen notaria**, y la política RLS prohibiria escribir en esa tabla sin
una. Van al canal de log `notaria_seguridad`.

### La bitacora nunca tumba una peticion

Si falla la escritura de la bitacora, se avisa por log y la peticion sigue su
curso. Un error de la bitacora no puede dejar a una persona de la notaria sin
poder trabajar.

### Conservacion

El log de seguridad se guarda **90 dias** en producción
(`LOG_DAILY_DAYS`), mas que el resto. Es el que sirve para responder reclamos.

---

## 4. Sesión y CSRF

| Medida | Detalle |
|---|---|
| La contrasena nunca pasa por aquí | La autenticación es OIDC contra Authentik. |
| Cookie `HttpOnly` | El navegador no la puede leer, lo que la saca del alcance de un XSS. |
| Cookie `Secure` | Viaja solo por HTTPS. **Obligatorio en producción.** |
| Cookie `SameSite=Lax` | Una peticion desde otro sitio no usa esta sesión. |
| Token CSRF | Laravel valida `X-XSRF-TOKEN`; el BFF lo copia automáticamente. |
| Estado firmado en el login | El destino del inicio de sesión lo firma la aplicación, no el navegador. |
| `strict` de sesión | Activado: una identificacion de sesión desconocida se rechaza. |
| Ventana del estado de login | 10 minutos, para que un estado robado ayer no sirva hoy. |

### La sesión no se autoaprovisiona

Si la identidad de Authentik es valida pero la persona **no esta dada de
alta**, el acceso se rechaza. Autoaprovisionar convertiria cualquier cuenta
del proveedor de identidad en acceso a expedientes notariales.

---

## 5. Validación y CSRF en la API

Todas las entradas pasan por `FormRequest` o `validate()`.

### Lo que hay que mirar en particular

| Campo | Validación |
|---|---|
| Archivos | `file`, `max:51200` (50 MB). Se comprueba el MIME. |
| `ruta_archivo` | **No se acepta desde la API.** La genera el servidor. |
| Montos | `numeric`, `min:0`. |
| Fechas | `date_format`. |
| Umbrales | `integer`, `min:1`, `max:3650`. |
| Números de protocolo | Se valida la **forma**, no el fondo. |

### Sobre el número de protocolo

```php
'numero_protocolo' => ['nullable', 'string', 'max:60', 'regex:/^[0-9A-Za-z\-\.\/ ]+$/'],
```

Esto comprueba la **forma**: letras, números, punto, guion y barra. **No
comprueba** si el número existe, ni si corresponde a esta operación, ni si
esta bien usado.

Es una validación de forma a propósito: esta aplicación **no conoce el
formato que usa cada notaria** para sus protocolos, y restringirlo mas seria
inventar una regla.

### El archivo no sale del volumen

`GestorDeDocumentos::rutaAbsolutaSegura()` resuelve la ruta y **comprueba que
siga dentro del volumen** antes de servir el archivo.

Es un control distinto del de la RLS, y hace falta: la política protege el
**dato**, no el **archivo**. Si se escribiera una ruta maliciosa en la base
(`../../../etc/passwd`), el filtro de la base no ayudaria.

nginx, ademas, **niega cualquier archivo `.php`, `.phtml` o `.phar`**: un
script guardado en el volumen de documentos se descarga o se rechaza, nunca
se ejecuta.

---

## 6. Autorización y respuestas 404

Cada endpoint llama a `authorize()`. Ver la matriz de roles en
[03](03-MULTITENANCY-Y-ROLES.md).

### Por que 404 y no 403

Un `403` confirmaria que el recurso existe. En una notaria, eso ya es filtrar
información: le diria a un intruso que hay un expediente con ese
identificador, aunque no pueda abrirlo.

Como la RLS ya impide que el recurso exista a ojos de la otra notaria, el
`404` es ademas la respuesta correcta en el caso habitual.

### Un control extra: la pertenencia

Dentro de una misma notaria, la RLS **no** impide cruzar recursos. Por eso
los endpoints comprueban la pertenencia:

```php
abort_unless($etapa->expediente_id === $expediente->id, 404);
```

Sin eso, alguien podria pasar el identificador de una etapa de otro
expediente del mismo tenant y moverla.

---

## 7. Datos de ejemplo

La semilla crea datos **inventados**: nombres, RUT, correos y documentos de
prueba.

**Ningun dato real de persona, de notaria o de operación debe entrar en esta
aplicación.** Si hay que cargar datos de prueba, se inventan. Un RUT real en
la base de desarrollo es un dato personal de una persona real, en un entorno
al que puede no tener acceso la gente que deberia.

---

## 8. Lo que este sistema NO hace, y sigue siendo riesgo

Con la misma claridad que en el resto del documento:

- **No hay revision de seguridad externa.** El código no ha pasado por una
  revision.
- **No hay pruebas de penetracion.**
- **Las pruebas de aislamiento estan escritas pero NO se ejecutaron**:
  no habia PostgreSQL en el entorno donde se escribio el código. Ver
  [10](10-PRUEBAS-Y-ESTADO.md).
- **La RLS no protege los logs.** Si un log de aplicación imprime un dato
  personal por un error, ese log hay que protegerlo como cualquier otro
  archivo sensible. Revisar `LOG_LEVEL` en producción.
- **El volumen de documentos no esta cifrado en reposo.** Si el disco del
  servidor se compromete, los archivos se leen. Cifrado en reposo es una
  decisión de infraestructura, no de esta aplicación.
- **No hay doble factor de autenticación.** Depende de como se configure
  Authentik.
- **No hay política de retencion implementada.** Cuanto tiempo se conservan
  los expedientes de una notaria que deja de usarla es una decisión **del
  cliente**, no algo que este sistema sepa.

---

## 9. Checklist antes de poner esto en una notaria

Esto **no** alcanza para production, y se escribe para que nadie lo confunda
con una lista que si alcanza:

- [ ] Revision de seguridad externa, con alguien que no escribio el código.
- [ ] Pruebas de penetracion.
- [ ] **Ejecutar la suite de aislamiento contra PostgreSQL real**, y ver que
      pasa. Ver [10](10-PRUEBAS-Y-ESTADO.md).
- [ ] `notaria:verificar-aislamiento` en cada despliegue, con resultado
      revisado por una persona.
- [ ] Confirmar que el rol de base de datos no es superusuario ni tiene
      `BYPASSRLS`.
- [ ] Cifrado en reposo para el volumen de documentos.
- [ ] Copias de seguridad probadas **y restauradas al menos una vez**.
      Un respaldo que nunca se restauro no es un respaldo. Ver
      [08](08-RESPALDOS.md).
- [ ] Decisión de retencion tomada por escrito, con el cliente.
- [ ] Que la notaria haya configurado sus umbrales, y sepa que no son plazos
      legales.
- [ ] Que alguien de la notaria haya leido el README entero, incluida la
      advertencia.