# 06 - Primer expediente

Este documento recorre un expediente completo, de punta a punta, para que se
vea como encajan las piezas.

> **Los nombres de las etapas, los tipos de operación y los números que se usan
> aca son EJEMPLOS INVENTADOS.** No son el procedimiento de ninguna operación
> real. Una notaria define los suyos. Ver
> [05](05-CONFIGURACION.md), seccion 4.

---

## 1. Antes de empezar

### La notaria tiene que configurar su umbral

Si no lo hizo, el semaforo **no marca nada** y el tablero muestra el aviso.
No es un error: es el sistema pidiéndole que le falta un número que solo
ella puede elegir.

```
Configuracion -> Umbrales del semaforo
  Dias antes de marcar en amarillo: 7
  Dias antes de marcar en rojo:    14
```

**Estos no son plazos legales.** Son el criterio de la notaria.

---

## 2. Abrir el expediente

```
POST /api/v1/expedientes
{
  "tipo_operacion_id": "<id>",
  "numero_protocolo": "4.512/2026",
  "descripcion": "Compraventa en Las Condes. El cliente pregunta seguido.",
  "profesional_id": "<id del notario>"
}
```

### Que pasa por dentro

`AperturadorDeExpediente::abrir()`:

1. **Genera el `folio` interno** (`EXP-00005`), que no sale de la aplicación.
2. **Crea el expediente.** El `numero_protocolo` lo escribe la persona; el
   sistema no lo genera y puede ir vacio.
3. **Copia las etapas de la plantilla** del tipo de operación.
4. **Resuelve los umbrales en cascada** para cada etapa copiada:
   plantilla -> tipo de operación -> notaria -> `NULL`.

Si la plantilla esta vacia, el expediente se abre **sin etapas** y la notaria
las agrega a mano. El sistema no inventa etapas.

### Resultado

| Etapa | Estado | Umbral |
|---|---|---|
| 1. Recepción de antecedentes | pendiente | 7 / 14 |
| 2. Revision de documentos | pendiente | 7 / 14 |
| 3. Elaboracion | pendiente | 7 / 14 |
| 4. Firma | pendiente | 7 / 14 |
| 5. Cierre y archivo | pendiente | 7 / 14 |

El tablero dice, en este momento: **nada atrasado**. Esta bien.

---

## 3. Mover el tramite

```
POST /api/v1/expedientes/{id}/etapas/{etapa_id}/estado
{ "estado": "terminada" }
```

`MaquinaDeEstadosEtapa::cambiar()` pone las fechas solo: al terminar, escribe
`terminada_en`. Nadie puede olvidar ponerla, y si la olvidara, el semaforo
seguiria contando esa etapa como espera **para siempre**.

Despues, `recalcularEstadoExpediente()` recalcula el estado del expediente. El
estado global **no se escribe a mano**.

---

## 4. Pedir un antecedente

```
POST /api/v1/expedientes/{id}/antecedentes
{
  "descripcion": "Certificado de vigencia de la compradora",
  "solicitado_a": "Registro Civil de Las Condes",
  "etapa_id": "<id de la etapa 2>",
  "responsable_id": "<id del protocolo>"
}
```

### Por que esto es un modulo aparte

Porque el problema real del negocio no es escribir: es que un expediente se
quede parado porque **nadie fue a buscar** lo que faltaba.

Registrar el antecedente por separado deja constancia de a quien se le pidio y
cuando. Si el cliente llama a preguntar, la respuesta no es "esta en proceso":

> "Se pidio el certificado de vigencia al Registro Civil de Las Condes el 29
> de septiembre. No ha llegado."

Eso es información. "Esta en proceso" no lo es.

---

## 5. Esperar: aquí aparece el semaforo

### La etapa 2 se pone en curso

```
POST /api/v1/expedientes/{id}/etapas/{etapa_id}/estado
{ "estado": "en_curso", "comentario": "Faltaba el certificado" }
```

### Que ve la persona cuando vuelve al tablero

| Dias | Nivel | Como se ve |
|---|---|---|
| 3 | verde | punto verde |
| 7 | **amarillo** | punto ambar |
| 14 | **rojo** | punto rojo |

Y en la fila aparece: **"lleva 12 dias en este estado"**, junto con
**"antecedentes sin llegar: 1"**.

Lo que **no** aparece: "vence en", "se vencio", "plazo legal". El sistema no
puede afirmar esas cosas.

### El sistema se dio cuenta solo

`RevisorDeSemaforo` corre cada hora (`notaria:revisar-semaforo`), y también
cuando alguien abre el tablero. Cuando la etapa cruza el umbral:

1. Se escribe `nivel_semaforo` y `dias_en_estado` en la etapa.
2. Se abre una **alerta** en `alertas_semaforo`, con la fecha en que cruzo.

La alerta se **actualiza** cada corrida, no se duplica. Si la etapa se
resuelve, la alerta se **cierra**. Si vuelve a atrasarse, se abre una nueva:
es un hecho distinto.

---

## 6. Los documentos

### Subir la primera versión

```
POST /api/v1/expedientes/{id}/documentos
  archivo: contrato-v1.pdf
  nombre: "Contrato de compraventa"
  fecha_documento: 2026-09-24
```

Se crea la ficha en `documentos` y la versión 1 en `documento_versiones`, con
`vigente_hasta = NULL`.

### Subir una versión nueva

```
POST /api/v1/documentos/{documento_id}/versiones
  archivo: contrato-v2.pdf
  observacion: "Se corrigio la clausula de eviccion"
```

Que pasa:

1. La versión 1 recibe `vigente_hasta = ahora`. **No se borra.**
2. Se crea la versión 2, vigente.
3. `documentos.version_actual` pasa a 2.

### Lo que se ve en pantalla

| Versión | Fecha | Estado | |
|---|---|---|---|
| v1 | 24-09-2026 | vigente hasta el 27-09-2026 | Descargar |
| v2 | 27-09-2026 | **vigente** | Descargar |

**Las dos se pueden descargar.** Ese es el punto: en una notaria, la pregunta
"que documento estaba vigente cuando se firmo" no es un detalle, es el
expediente.

### Y el boton de borrar no existe

No hay ruta `DELETE` de documentos. No hay `destroy()`. No hay metodo `borrar`.

No es una omision: es la regla del sistema. Hay dos pruebas que lo vigilan
(`DocumentosNoSeBorranTest`), incluida una que falla si alguien agrega la
ruta.

---

## 7. Las partes, y el rol que vive en la relacion

### Crear la persona

```
POST /api/v1/partes
{ "nombre": "Maria", "apellidos": "Gonzalez", "rut": "11.111.111-1" }
```

### Asignarle un rol **en este expediente**

```
POST /api/v1/partes/{parte_id}/roles
{ "expediente_id": "<id>", "rol": "compradora", "es_principal": true }
```

### Y despues, el mismo expediente

```
POST /api/v1/partes/{misma_parte_id}/roles
{ "expediente_id": "<mismo id>", "rol": "testigo" }
```

Maria Gonzalez queda con **una sola ficha de persona** y **dos roles en el
mismo expediente**, porque el rol es de la relacion, no de la persona.

Si el rol estuviera en `partes`, habria que duplicarla, y la duplicación, con
el tiempo, produce expedientes cruzados.

---

## 8. Cerrar

```
POST /api/v1/expedientes/{id}/cerrar
```

El estado del expediente **se deriva de las etapas**. Si queda alguna
pendiente, la API responde `409`:

```json
{
  "mensaje": "El expediente no se puede cerrar: todavia tiene etapas sin terminar."
}
```

Cuando todas las etapas estan terminadas, el expediente queda `cerrado` y se
guarda `fecha_cierre`.

### Lo que significa "cerrado"

> La **notaria dio el tramite por terminado.**

Y nada mas. El sistema **no declara** que la operación sea valida, ni que los
documentos esten bien, ni que el plazo se haya cumplido. No puede: no conoce
la ley aplicable.

Por eso la accion se llama `cerrar` y no `legalizar`. El nombre de una accion
enseña.

---

## 9. Lo que queda en el historial

| Que | Donde |
|---|---|
| Que se pidio y cuando | `antecedentes` |
| Que documento estaba vigente y cuando | `documento_versiones.vigente_hasta` |
| Cuando empezo y termino cada etapa | `etapas.iniciada_en`, `terminada_en` |
| Cuando se cruzo cada umbral | `alertas_semaforo.umbral_cruzado_en` |
| Quien hizo cada cambio | `bitacora_accesos` |
| Que se registro y cuando | `cobranzas`, con la referencia real |

Ninguna de esas cosas se borra. Ese es el motivo por el que el sistema no
tiene un boton de borrar en casi ninguna parte.

---

## 10. Que se ve despues, un mes mas tarde

Alguien pregunta en la reunion: "el contrato que firmamos en septiembre, era
el del 24 o el del 27?"

La respuesta esta a un click: el expediente tiene las dos versiones, con las
fechas, y se ve claramente cual era la vigente el dia de la firma.

Sin historial documental, esa pregunta no tiene respuesta, y con ella, el
expediente deja de valer.