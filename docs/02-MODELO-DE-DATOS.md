# 02 - Modelo de datos

## 1. El mapa

```
notarias (tenant)
  |
  +-- usuarios                    rol: administrador | notario | protocolo | auxiliar
  +-- profesionales               quien recibe agenda (puede no tener cuenta)
  +-- configuracion_notaria       umbrales + DATOS POR CONFIRMAR (vacios)
  |
  +-- tipos_operacion             el catalogo lo define la notaria
  |     +-- etapas_tipo_operacion    PLANTILLA de etapas y umbrales
  |
  +-- expedientes                 EL CENTRO
  |     +-- etapas                   AQUI VIVE EL SEMAFORO
  |     |     +-- antecedentes
  |     |     +-- alertas_semaforo
  |     +-- antecedentes
  |     +-- documentos
  |     |     +-- documento_versiones
  |     +-- expediente_partes  ---> partes
  |     +-- cobranzas
  |     +-- agenda_citas
  |
  +-- partes
  +-- bitacora_accesos
  +-- contadores
```

Todas las tablas de negocio llevan `tenant_id` y tienen **Row Level
Security**. Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## 2. Lo que NO tiene datos inventados

Tres columnas nacen en `NULL` en todo el sistema, y no es una falta de
tiempo:

| Columna | Por que en `NULL` |
|---|---|
| `expedientes.numero_protocolo` | Lo pone la notaria. El sistema no lo genera. |
| `configuracion_notaria.dias_alerta_por_defecto` | Sin umbral, el semaforo no marca nada. |
| `configuracion_notaria.parametros_por_confirmar.*.valor` | Poner un valor de ejemplo seria inventar un plazo. |

Las factories y las semillas respetan lo mismo: `ExpedienteFactory` deja
`numero_protocolo` en `NULL` a propósito, y `NotariaDemoSeeder` crea los
umbrales en `NULL`.

---

## 3. expedientes

El centro del sistema.

| Columna | Que es |
|---|---|
| `folio` | Identificador **interno**, generado por el sistema. No sale de la aplicación. |
| `numero_protocolo` | Lo escribe la notaria. Puede ir vacio al abrir el expediente. |
| `tipo_operacion_id` | Que tipo de operación es. |
| `estado` | `en_tramite`, `cerrado`, `anulado`. **Se deriva de las etapas.** |
| `fecha_inicio` / `fecha_cierre` | |
| `dias_alerta_por_defecto` / `dias_critico_por_defecto` | Copia del umbral al abrirse. |

### Por que el estado se deriva

Poder marcar "cerrado" con etapas abiertas por el camino es el tipo de dato
que despues nadie sabe defender. `MaquinaDeEstadosEtapa::recalcularEstadoExpediente()`
lo calcula.

### Por que los umbrales se copian y no se heredan

Si la notaria cambia su umbral por defecto manana, los expedientes abiertos
hoy conservan el criterio con el que se estan midiendo. Si se heredara en
caliente, el tablero saltaria de color a todo el mundo de golpe un lunes por
la manana, y la primera conclusion seria "el sistema se rompio".

Los expedientes nuevos si toman el valor nuevo.

---

## 4. etapas

Aquí vive el semaforo.

| Columna | Que es |
|---|---|
| `orden` | Posicion en el tramite. Único por expediente. |
| `nombre` | Lo define la notaria. |
| `estado` | `pendiente`, `en_curso`, `bloqueada`, `terminada`, `omitida`. |
| `dias_alerta` | Umbral N. `NULL` = la etapa no se marca. |
| `dias_critico` | Umbral M. `NULL` = nunca hay rojo. |
| `iniciada_en` / `terminada_en` | Las fechas con las que el semaforo mide. |
| `dias_en_estado` | Derivado, escrito por el semaforo. |
| `nivel_semaforo` | Derivado. |
| `requiere_antecedente` | Si esta etapa espera algo externo. |

### `bloqueada` esta separada de `pendiente` a propósito

Una etapa bloqueada espera por una cosa concreta, y en general tiene un
antecedente asociado. Mezclarlas hacia imposible ver en el tablero "esto
espera porque nadie fue a buscar algo", que es justo el problema que el
producto viene a resolver.

### Las fechas las pone el sistema al cambiar el estado

`MaquinaDeEstadosEtapa::cambiar()` las mantiene coherentes: al terminar, pone
`terminada_en`; al volver a un estado que espera, la limpia. Si alguien
termina una etapa y no se pone la fecha, el semaforo la seguiria contando
como espera para siempre.

### `dias_alerta` se resuelve en cascada al abrir el expediente

1. El umbral de la plantilla de la etapa, si tiene.
2. Si no, el del tipo de operación.
3. Si no, el de la notaria.
4. Si no hay ninguno, queda `NULL`, y esa etapa no se marca.

---

## 5. partes y expediente_partes

**`partes` NO tiene rol.**

```
partes
  id, tenant_id, nombre, apellidos, rut, correo, telefono, direccion
```

```
expediente_partes
  id, tenant_id, expediente_id, parte_id, ROL, es_principal
  UNIQUE (expediente_id, parte_id, rol)
```

Maria Gonzalez puede ser compradora en un expediente y testigo en otro, con
**una sola ficha de persona**. Si el rol estuviera en `partes`, habria que
duplicarla, y la duplicación produce expedientes cruzados.

`rol` es texto libre. El sistema trae algunos sugeridos en el formulario, pero
**no restringe la lista**: los roles que una operación reconoce dependen de la
operación y del marco aplicable, y este sistema no conoce ese marco.

La misma lógica se repite en `solicitado_a` (antecedentes) y en
`profesional_responsable_id`: atributos de la relacion, no de la persona.

---

## 6. documentos y documento_versiones

La regla: **un documento de un expediente nunca se borra.**

```
documentos                          FICHA LOGICA
  id, tenant_id, expediente_id, nombre, tipo
  estado           vigente | reemplazado
  version_actual   int
  reemplazo: reemplazado_por, reemplazado_en, reemplazado_por_documento_id

documento_versiones                CADA ARCHIVO
  id, tenant_id, documento_id, version
  ruta_archivo (relativa), nombre_archivo, mime, tamano_bytes, hash_sha256
  vigente_hasta    NULL = es la version vigente
  UNIQUE (documento_id, version)
```

Un documento con tres versiones tiene **tres filas** en
`documento_versiones` y **una** en `documentos`. Las dos primeras quedan con
`vigente_hasta` distinto de `NULL`: dejaron de ser la buena, pero siguen
descargables.

`hash_sha256` detecta alteracion del archivo en disco y sirve para validar un
respaldo restaurado. No es una firma criptografica: detecta corrupcion y
cambios accidentales.

No existe ninguna forma de borrar ni una fila ni la otra: no hay ruta DELETE,
no hay `destroy()`, no hay metodo `borrar` en el servicio.

---

## 7. antecedentes

Lo que hay que conseguir.

| Columna | Que es |
|---|---|
| `descripcion` | Que falta. |
| `solicitado_a` | A quien se le pidio. **Texto libre**: se pide a registros que pueden no estar en el sistema (otro notario, un municipal, un archivo). |
| `estado` | `pendiente`, `recibido`, `rechazado`, `descartado`. |
| `solicitado_en` / `recibido_en` | |
| `etapa_id` | Que etapa lo espera. |

`rechazado` y `descartado` no son como `pendiente`: son decisiones que la
notaria registro, y quedan para siempre. Un expediente que avanza "saco lo que
no sirvio" sin dejar rastro no se puede defender despues.

---

## 8. alertas_semaforo

El registro de que el sistema se dio cuenta solo.

| Columna | Que es |
|---|---|
| `nivel` | `amarillo` o `rojo`. |
| `dias_en_estado` | Al abrir la alerta, y se actualiza. |
| `umbral_cruzado_en` | Cuando cruzo el umbral. |
| `veces_revisada` | Cuantas corridas lo actualizaron. |
| `cerrada_en` / `motivo_cierre` | Cuando dejo de estar atrasada. |

Una alerta **abierta** por etapa y nivel, impuesto en la base:

```sql
CREATE UNIQUE INDEX alertas_semaforo_abierta_por_etapa_nivel
    ON public.alertas_semaforo (etapa_id, nivel)
    WHERE cerrada_en IS NULL
```

Es un indice **parcial**: solo aplica a las filas abiertas. Por eso correr el
semaforo diez veces no genera diez alertas, actualiza la misma. Y si la etapa
se resuelve y vuelve a atrasarse, se abre una nueva, que es un hecho distinto.

---

## 9. configuracion_notaria

| Columna | Que es |
|---|---|
| `dias_alerta_por_defecto` / `dias_critico_por_defecto` | Umbrales por defecto. `NULL` = apagado. |
| `aviso_umbral_no_configurado` | Texto que se muestra cuando no hay umbral. |
| `parametros_por_confirmar` | Los datos que un notario debe cargar. |

### `parametros_por_confirmar`: la tabla de lo que el sistema NO sabe

```json
{
  "dias_espera_antecedente_no_respondido": {
    "etiqueta": "Dias que la notaria espera un antecedente antes de alarmarse",
    "valor": null,
    "fundamento": null,
    "confirmado_por": null,
    "confirmado_en": null
  }
}
```

Se siembran **solo las claves**. El `valor` lo carga un notario de la
notaria, y el `fundamento` es con que criterio lo fijo: un dato sin el
criterio con el que se establecio no sirve para sostener nada mas adelante.

Esta es la respuesta del sistema a "no inventes plazos legales": el campo
existe, se muestra, dice lo que hay que confirmar, y espera.

---

## 10. agenda_citas

| Columna | Que es |
|---|---|
| `profesional_id` | Quien atiende. |
| `estado` | `propuesta`, `confirmada`, `realizada`, `cancelada`, **`bloqueo`**. |
| `inicio` / `fin` / `duracion_minutos` | |
| `expediente_id` | Opcional: una atención puede o no estar pegada a un expediente. |
| `bloqueo` | Motivo, si es bloqueo. |

`bloqueo` es tiempo que la notaria se guarda para si (reunion, perpetual). No
tiene expediente. Se muestra en la agenda para que no se agende encima.

Las citas **no se borran**: se cancelan. Una cita cancelada queda marcada.

`duracion_minutos` es cuanto dura la reunion. **No es un plazo legal.**

---

## 11. cobranzas

Registro de lo facturado. Y nada mas.

```
concepto, monto, moneda, fecha_emision, estado, parte_id, expediente_id,
referencia_documento_tributario, observacion
```

**No hay columna que represente un documento electronicamente emitido,
timbado, anulado o enviado.** Hefesto tiene el kit LibreDTE, pero su versión
publicada es una beta y su API es de solo lectura: no permite automatizar la
emision. No hay código que lo haga, ni lo habra.

`referencia_documento_tributario` es texto: lo que la notaria **ya emitio por
su cuenta**, con su número real. Es un respaldo contable, no un emisor.

---

## 12. tablas sin RLS

| Tabla | Por que |
|---|---|
| `notarias` | Se consulta en el login, cuando no hay sesión y no hay `app.tenant_id` que poner. Compensada con la vista `vw_notarias_publicas`, que expone solo `slug`, `nombre` y `activo`. |
| `sessions` | Es la sesión de la capa de sesión: tiene que poder escribirse antes de saber de que notaria viene la persona. |

Las dos son excepciones **deliberadas y documentadas**. Ver
[03](03-MULTITENANCY-Y-ROLES.md), seccion "El punto ciego".

---

## 13. Indices que importan

| Indice | Para que |
|---|---|
| `expedientes (tenant_id, estado)` | El listado de abiertos. |
| `etapas (tenant_id, estado, iniciada_en)` | El semaforo y el tablero de atrasos. |
| `etapas (tenant_id, nivel_semaforo)` | Filtrar el tablero por nivel. |
| `documentos (tenant_id, expediente_id)` | El listado documental del expediente. |
| `bitacora_accesos (tenant_id, expediente_id, registrado_en)` | "Quien entro a este expediente y cuando". |
| `alertas_semaforo` parcial | Una alerta abierta por etapa y nivel. |