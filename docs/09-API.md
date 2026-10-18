# 09 - API

Base: `/api/v1`. Todas las rutas exigen sesión, excepto las de entrada.

Formato: JSON.

---

## 1. Convenciones

| Tema | Regla |
|---|---|
| Errores de validación | `422`, con `{ "mensaje": ..., "errores": { campo: [mensajes] } }` |
| Recurso de otra notaria | **`404`**, nunca `403`. Ver seccion 2. |
| Sin sesión | `401` |
| Recurso inexistente | `404` |
| Lista paginada | `{ "data": [...], "total": N }` |

### El formato de los errores

```json
{
  "mensaje": "El numero de protocolo solo admite letras, numeros, punto, guion y barra.",
  "errores": {
    "numero_protocolo": [
      "El numero de protocolo solo admite letras, numeros, punto, guion y barra."
    ]
  }
}
```

La interfaz muestra `mensaje`, y si no hay, el primer valor de `errores`. El
error crudo de PHP **nunca** se muestra: puede traer detalles internos.

---

## 2. Por que 404 y no 403

Un `403` confirma que el recurso existe. En una notaria, eso ya es filtrar
información: le dice a un intruso que hay un expediente con ese identificador,
aunque no pueda abrirlo.

Como la RLS ya impide que el recurso exista a ojos de la otra notaria, el
`404` es ademas la respuesta correcta.

---

## 3. Entrada

| Metodo | Ruta | Sin sesión |
|---|---|---|
| `GET` | `/auth/notarias` | si |
| `GET` | `/auth/entrar/{slug}` | si |
| `GET` | `/auth/callback` | si |
| `POST` | `/auth/salir` | no |

`GET /auth/notarias` devuelve **solo** `slug` y `nombre`, desde la vista
`vw_notarias_publicas`. Antes del login no hay motivo para que el navegador
vea RUT, direccion, correo ni telefono.

`POST /auth/salir` cierra la sesión.

---

## 4. El tablero

```
GET /tablero
```

El endpoint que justifica el producto.

```json
{
  "sin_umbral": false,
  "items": [
    {
      "etapa_id": "...",
      "expediente_id": "...",
      "folio": "EXP-00001",
      "numero_protocolo": "4.512/2026",
      "tipo_operacion": "Ejemplo: compraventa",
      "etapa": "Revision de documentos",
      "estado": "en_curso",
      "nivel": "rojo",
      "dias_en_estado": 18,
      "dias_alerta": 7,
      "dias_critico": 14,
      "antecedentes_pendientes": 1
    }
  ],
  "conteo": { "rojo": 1, "amarillo": 0, "verde": 0 },
  "alertas_abiertas": 1,
  "expedientes_abiertos": 2,
  "antecedentes_pendientes": 1
}
```

### Cuando `sin_umbral` es `true`

```json
{
  "sin_umbral": true,
  "aviso": "Esta notaria todavia no configuro cuantos dias espera antes de alarmarse. ...",
  "items": [],
  "conteo": { "rojo": 0, "amarillo": 0, "verde": 0 }
}
```

El tablero llega **vacio y con la explicacion**, no con colores inventados.

---

## 5. Expedientes

| Metodo | Ruta | Rol minimo |
|---|---|---|
| `GET` | `/expedientes` | cualquiera |
| `POST` | `/expedientes` | cualquiera |
| `GET` | `/expedientes/{id}` | cualquiera |
| `PATCH` | `/expedientes/{id}` | protocolo |
| `POST` | `/expedientes/{id}/cerrar` | **notario** |
| `POST` | `/expedientes/{id}/anular` | **notario** |
| `GET` | `/expedientes/{id}/etapas` | cualquiera |
| `POST` | `/expedientes/{id}/etapas/{etapa}/estado` | protocolo |
| `PATCH` | `/expedientes/{id}/etapas/{etapa}/umbrales` | protocolo |

### Filtros de `GET /expedientes`

| Parámetro | Efecto |
|---|---|
| `estado` | Filtra por estado. |
| `tipo_operacion` | Filtra por tipo. |
| `buscar` | Busca en folio, número de protocolo y descripcion. |
| `por_pagina` | Por defecto 25. |

### `POST /expedientes`

```json
{
  "tipo_operacion_id": "...",
  "numero_protocolo": "4.512/2026",
  "descripcion": "...",
  "profesional_id": "...",
  "fecha_inicio": "2026-09-23"
}
```

`numero_protocolo` es **opcional**: es normal que al abrir todavia no exista.
El sistema **no lo genera**. El `folio` interno si, y no sale de la
aplicación.

Al abrir, se copian las etapas de la plantilla y se resuelven los umbrales en
cascada.

### `POST /expedientes/{id}/cerrar`

Si quedan etapas esperando, responde `409`:

```json
{
  "mensaje": "El expediente no se puede cerrar: todavia tiene etapas sin terminar."
}
```

Cuando se cierra, la respuesta dice exactamente que significa:

> "Expediente cerrado por la notaria. Esto registra que la notaria dio el
> tramite por terminado; no declara que la operación sea valida."

### `POST /expedientes/{id}/etapas/{etapa}/estado`

```json
{ "estado": "en_curso", "comentario": "Faltaba el certificado" }
```

El estado de la etapa se valida contra la lista conocida. **No se valida que
el cambio tenga sentido para el tramite**: ese orden lo define la notaria en
su plantilla, y esta aplicación no lo conoce.

### `PATCH /expedientes/{id}/etapas/{etapa}/umbrales`

```json
{ "dias_alerta": 5, "dias_critico": 12 }
```

Se puede mandar `null` para quitar el umbral: la etapa deja de marcarse.

Si `dias_critico` es menor que `dias_alerta`, responde `422`.

---

## 6. Antecedentes

| Metodo | Ruta |
|---|---|
| `GET` | `/expedientes/{id}/antecedentes` |
| `POST` | `/expedientes/{id}/antecedentes` |
| `PATCH` | `/antecedentes/{id}` |

`solicitado_a` es texto libre a propósito: se pide a registros que pueden no
estar en el sistema (otro notario, un municipal, un archivo).

La fecha de recepción solo se guarda si el estado pasa a `recibido`.

---

## 7. Documentos

| Metodo | Ruta |
|---|---|
| `GET` | `/expedientes/{id}/documentos` |
| `POST` | `/expedientes/{id}/documentos` |
| `POST` | `/documentos/{id}/versiones` |
| `POST` | `/documentos/{id}/reemplazar` |
| `GET` | `/documentos/{id}/versiones/{version}/descargar` |

### LAS RUTAS QUE NO EXISTEN

**No hay ninguna ruta `DELETE` de documentos.**

No es una omision: es la regla del sistema. Hay una prueba
(`test_la_api_no_tiene_ninguna_ruta_delete_de_documentos`) que **falla** si
alguien agrega una.

Lo que hay en su lugar:

| Operación | Que hace |
|---|---|
| `POST /documentos/{id}/versiones` | Sube una versión nueva. La anterior queda con `vigente_hasta`, y sigue descargable. |
| `POST /documentos/{id}/reemplazar` | Marca el documento como reemplazado. **El registro queda**, con la fecha y con quien lo hizo. |

Tampoco hay `DELETE` de expedientes: uno mal abierto se **anula**.

### `GET /expedientes/{id}/documentos`

Devuelve el historial **completo**, incluyendo los reemplazados, y la respuesta
lo dice:

```json
{
  "datos": [ ... ],
  "vigentes": 1,
  "reemplazados": 1,
  "aviso": "Un documento de un expediente no se borra: se reemplaza. El historial queda completo."
}
```

La API **no filtra** los reemplazados por convenience de la interfaz: si lo
hiciera, la pantalla no tendria forma de mostrar que existieron.

### `GET /documentos/{id}/versiones/{version}/descargar`

1. Autoriza.
2. Comprueba que la versión **pertenece** al documento (la RLS no impide
   cruzar recursos dentro de la misma notaria).
3. Resuelve la ruta y comprueba que **no sale del volumen**.
4. Sirve el archivo.

---

## 8. Partes

| Metodo | Ruta | Rol minimo |
|---|---|---|
| `GET` | `/partes` | cualquiera |
| `POST` | `/partes` | protocolo |
| `PATCH` | `/partes/{id}` | protocolo |
| `POST` | `/partes/{id}/roles` | protocolo |

### `POST /partes/{id}/roles`

```json
{
  "expediente_id": "...",
  "rol": "compradora",
  "es_principal": true
}
```

El rol se asigna **en un expediente**. `rol` es texto libre: el sistema trae
sugeridos en el formulario, pero no restringe la lista, porque los roles que
una operación reconoce dependen de la operación y del marco aplicable.

La misma persona puede recibir roles distintos en expedientes distintos, con
**una sola ficha**.

---

## 9. Agenda

| Metodo | Ruta |
|---|---|
| `GET` | `/agenda` |
| `POST` | `/agenda` |
| `PATCH` | `/agenda/{id}` |
| `POST` | `/agenda/{id}/cancelar` |
| `GET` | `/agenda/profesionales` |

Filtros de `GET /agenda`: `desde`, `hasta`, `profesional_id`.

Las citas **no se borran**: se cancelan. Una cita cancelada queda en la agenda
marcada, para que se vea que existio.

---

## 10. Cobranzas

| Metodo | Ruta | Rol minimo |
|---|---|---|
| `GET` | `/expedientes/{id}/cobranzas` | cualquiera |
| `POST` | `/expedientes/{id}/cobranzas` | **notario** |
| `PATCH` | `/cobranzas/{id}` | **notario** |

### Lo que este registro NO es

**No emite documentos tributarios electronicos.** No los timbra, no los anula,
no los envia.

El kit LibreDTE de Hefesto tiene su API en **solo lectura**, así que no se
puede automatizar la emision. No hay código que lo haga, ni lo habra.

`referencia_documento_tributario` es texto: el número real que **la notaria ya
emitio por su cuenta**.

La respuesta del `POST` lo dice:

> "Cobro registrado. Esta aplicación NO emitio ningun documento tributario
> electrónico: queda registrado lo que la notaria ya emitio."

Ver [05](05-CONFIGURACION.md), seccion 5.

---

## 11. Configuración

| Metodo | Ruta | Rol minimo |
|---|---|---|
| `GET` | `/configuracion` | cualquiera |
| `PATCH` | `/configuracion` | **notario** |
| `POST` | `/configuracion/parametros/{clave}` | **notario** |

### `GET /configuracion`

Devuelve los umbrales, la advertencia, y `parametros`, que es el mapa de los
datos por confirmar.

```json
{
  "datos": {
    "dias_alerta_por_defecto": 7,
    "dias_critico_por_defecto": 14
  },
  "advertencia": "Estos umbrales son una alarma interna de la notaria. NO son plazos legales...",
  "parametros": {
    "dias_espera_antecedente_no_respondido": {
      "etiqueta": "...",
      "valor": null,
      "fundamento": null,
      "confirmado_por": null,
      "confirmado_en": null,
      "ayuda": "Es un criterio INTERNO de la notaria. No es un plazo legal..."
    }
  }
}
```

### `POST /configuracion/parametros/{clave}`

```json
{ "valor": "10", "fundamento": "Criterio interno acordado en reunion del 12-09-2026." }
```

**Ambos campos son obligatorios.** El valor lo decide la notaria, y el
fundamento deja constancia de con que criterio se fijo.

Al guardar quedan registrados **quien** confirmo y **cuando**.

---

## 12. Salud

```
GET /salud
```

Sin sesión. Devuelve `{"estado": "ok", "servicio": "api-notaria"}`.

---

## 13. Resumen de lo que la API no hace

| No hay | Por que |
|---|---|
| `DELETE` de documentos | Un documento de un expediente no se borra: se reemplaza. |
| `DELETE` de expedientes | Uno mal abierto se anula. |
| `DELETE` de partes | Se conserva el historial. |
| Emision de documentos tributarios | La API del kit es de solo lectura. |
| Edicion de la bitacora | Es de solo agregar, por definicion. |
| Validación de que un tramite sea valido | Esta aplicación no interpreta la ley. |
| Plazos legales | No hay ninguno, y no habra. |