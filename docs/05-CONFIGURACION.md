# 05 - Configuración

## 1. LA ADVERTENCIA QUE DEFINE ESTE DOCUMENTO

**Esta aplicación no reemplaza ni interpreta la ley, y no puede.**

No es una limitacion de la versión. Es una limitacion de naturaleza: un
programa no sabe que es un testamento, ni que documentos exige una operación,
ni cuanto tiempo tiene el interesado para concurrir.

Por eso, en todo el sistema:

- **No hay ningun plazo legal en el código.** Ni como constante, ni como valor
  por omision en un archivo de configuración.
- **No hay ningun valor legal de ejemplo.** Donde un dato deberia existir y
  esta aplicación no lo sabe, el campo queda **vacio**, y se documenta que un
  notario tiene que cargarlo.
- **Lo que el sistema hace es gestionar y registrar lo que ya existe.** No
  dice que un tramite sea valido.

Donde hay un dato que el sistema no puede saber, esta **configurable**, y la
pantalla lo muestra vacio diciendo que hay que confirmarlo.

---

## 2. Los umbrales del semaforo

Son **lo mas importante que se configura**, y lo que mas se puede malentender.

| Parámetro | Significado |
|---|---|
| `dias_alerta_por_defecto` | Dias que la notaria espera antes de que una etapa aparezca en **amarillo**. Es el **N**. |
| `dias_critico_por_defecto` | Dias antes de que aparezca en **rojo**. Es el **M**. |

### Son una alarma interna, NO un plazo legal

Un umbral configurado aquí significa:

> "Esta notaria decide que, si una etapa lleva mas de N dias en este estado,
> conviene mirarla."

Y nada mas. **No significa:**

- que algo se haya vencido;
- que se haya incumplido un plazo;
- que el tramite este mal;
- que el documento sea invalido.

El texto de la interfaz lo dice siempre, y hay una prueba que lo verifica
(`test_el_resumen_no_afirma_ningun_vencimiento`).

### Que pasa si no se configuran

**No se marca nada.** El nivel queda en `sin_umbral` y el tablero muestra el
aviso pidiendo configurarlos.

Es la conducta deliberada del sistema. Un tablero con huecos se nota y se
corrige; un tablero con números falsos se usa. Ver
[01](01-ARQUITECTURA.md), seccion "El semaforo".

### Que pasa si solo se configura N

El **rojo nunca aparece**. La etapa queda en amarillo para siempre.

Es intencional: un rojo por omision seria inventar un segundo plazo que
nadie eligio.

### Que pasa si el critico es menor que la alerta

El sistema usa **el mayor de los dos**. Una etapa nunca puede estar en rojo
sin haber pasado antes por amarillo.

### El cambio no reescribe lo abierto

Los umbrales se **copian** a las etapas al abrir el expediente. Cambiar el
valor por defecto afecta a los expedientes **nuevos**, no a los ya abiertos.

El motivo: si se heredaran en caliente, el tablero saltaria de color a todo el
mundo de golpe un lunes por la manana, y la primera conclusion seria "el
sistema se rompio".

---

## 3. Los datos que un notario debe confirmar

Esta es la parte del sistema que **no puede** hacer su trabajo, y que por eso
esta expuesta en pantalla en vez de escondida.

`configuracion_notaria.parametros_por_confirmar` guarda las **claves** de los
datos que la notaria debe definir, con el **valor en `NULL`**.

```json
{
  "dias_espera_antecedente_no_respondido": {
    "etiqueta": "Dias que la notaria espera un antecedente antes de alarmarse",
    "valor": null,
    "fundamento": null,
    "confirmado_por": null,
    "confirmado_en": null
  },
  "dias_revision_documentacion": { ... },
  "dias_para_citar_partes": { ... }
}
```

### Por que vienen vacios

Porque **sembrarlos seria inventar plazos**. Si la semilla trajera
`"valor": 15`, habria una pantalla mostrando "15 dias" sin que nadie lo haya
decidido, y alguien lo leeria como un plazo. El sistema no hace eso.

### Que carga la persona

| Campo | Quien lo carga | Que es |
|---|---|---|
| `valor` | un notario o el administrador | El número que la notaria decide. |
| `fundamento` | la misma persona | **Con que criterio se fijo.** |

El `fundamento` no es burocracia. Un dato sin el criterio con el que se
establecio no sirve para sostener nada mas adelante. Si el valor depende de una
norma, el fundamento es lo que deja constancia de cual se aplico.

Al guardar, quedan registrados **quien** confirmo y **cuando**.

### Estos NO son los umbrales del semaforo

`dias_espera_antecedente_no_respondido` es un criterio que la notaria anota
para su propio control. **No alimenta el semaforo.** El semaforo usa
unicamente `dias_alerta` y `dias_critico`, que se configuran en la seccion 2.

Se mantubieron separados a propósito: los umbrales son un mecanismo interno del
sistema, y estos datos son un registro de criterios de la notaria. Si un dia
alguien quisiera que un criterio alimentara el semaforo, que sea una decisión
deliberada y visible, no un efecto colateral de guardar un dato.

---

## 4. Catalogos que define la notaria

### Tipos de operación

**No hay catalogo impuesto.** Cada notaria define los tipos que atiende, con
el nombre que ella usa.

Se siembran dos, **inventados**, solo para que la pantalla tenga algo:

| Código | Nombre | Nota |
|---|---|---|
| `EJ-COMP` | Ejemplo: compraventa | Sus etapas son de ejemplo. |
| `EJ-TEST` | Ejemplo: testamento | Tiene umbral configurado, para ver el semaforo funcionando. |

**Una notaria no debe copiar estos nombres ni estas etapas.** Cada una define
las suyas. El sistema no sabe que etapas tiene una operación, ni en que orden,
ni que documentos exigen.

### Etapas por tipo de operación

Los nombres de las etapas los pone la notaria. Al abrir un expediente se
**copian** a `etapas`, de modo que:

- se puede corregir la plantilla sin tocar los expedientes ya abiertos;
- cada expediente conserva las etapas con las que se abrio.

`requiere_antecedente` marca las etapas que esperan algo externo. Es lo que
permite que el tablero diga "faltan 2 antecedentes", que es el problema real
del negocio.

### Roles de las partes

`expediente_partes.rol` es **texto libre**. El sistema trae sugeridos en el
formulario, pero **no restringe la lista**: los roles que una operación
reconoce dependen de la operación y del marco aplicable, y este sistema no
conoce ese marco.

---

## 5. Facturacion electrónica: lo que NO se hace

### La situacion

Hefesto tiene el kit **LibreDTE**. Su versión publicada es una **beta** y su
API es de **solo lectura**: no permite automatizar la emision.

Por lo tanto, **no hay, en ninguna parte de esta aplicación, código que**:

- emita un documento tributario electrónico;
- lo timbre;
- lo anule;
- lo envie a la autoridad;
- lo muestre al cliente como emitido.

Prometer automatizacion seria prometer algo que no se puede hacer.

### Lo que si hay

Un **registro** de lo que la notaria ya emitio por su cuenta:

```
cobranzas
  concepto, monto, moneda, fecha_emision, estado
  referencia_documento_tributario  <- texto: el numero REAL que ya existe
  parte_id, expediente_id, observacion
```

La notaria escribe a mano lo que emitio, con su número real. Es un **respaldo
contable**, no un emisor.

### Por que estadecision y no otra

Las alternativas eran:

| Alternativa | Por que no |
|---|---|
| Integrar LibreDTE | Su API es de solo lectura. No se puede automatizar. |
| Inventar un emisor propio | Escribir un emisor de documentos tributarios es un problema serio por si solo, y hacerlo sin autorización ni pruebas seria irresponsable. |
| Dejar el campo vacio para siempre | La notaria necesita un respaldo de lo que facturo. El registro es eso, y nada mas. |

### Si el kit pública una API de escritura

Eso es un **proyecto aparte**, no una mejora de este. Habria que evaluar:

- autorización y firma digital;
- certificacion y pruebas del emisor;
- manejo de errores del acceptor, que es asincrono;
- y decidir si Hefesto asume esa responsabilidad frente a una notaria.

Mientras tanto, `docs/05` es la guia: **registro, no emision**.

---

## 6. Variables de entorno

| Variable | Para que | Riesgo si se cambia mal |
|---|---|---|
| `APP_KEY` | Cifra la cookie y firma el estado del inicio de sesión. | Con la misma clave en dos instalaciones, un estado firmado sirve en las dos. |
| `DB_USERNAME` / `DB_PASSWORD` | Conexión a PostgreSQL. | Si el rol es superusuario, **la RLS no se aplica**. |
| `DOCUMENTOS_PATH` | Raiz del volumen de documentos. | Si apunta a un directorio equivocado, los archivos no se encuentran o se sirven de mas. |
| `AUTHENTIK_CLIENT_SECRET` | Credencial ante el proveedor de identidad. | Fuga: cualquiera puede hacerse pasar por la aplicación. |
| `SESSION_SECURE_COOKIE` | **`true` en producción.** | Con `false`, la cookie viaja en texto plano. |
| `LOG_NOTARIA_SEGURIDAD` | Canal de los hechos previos al login. | Con `warning`, no quedan registrados. |

Ningun valor de `.env.example` es real. Ver `api/.env.example`.

---

## 7. Resumen: que se configura y que no

| Cosa | Configurable | Por |
|---|---|---|
| Dias de alerta y critico del semaforo | **si** | Criterio interno de la notaria. |
| Dias que espera un antecedente | **si**, como registro | Criterio interno, con fundamento. |
| Dias que tarda la revision documental | **si**, como registro | Criterio interno, con fundamento. |
| Dias entre citacion y firma | **si**, como registro | Criterio interno, con fundamento. |
| Tipos de operación | **si** | Los define la notaria. |
| Etapas de cada tipo | **si** | Las define la notaria. |
| Rol de una parte en una operación | **si**, texto libre | Depende de la operación y del marco aplicable. |
| Plazos legales | **no, y no habra** | Esta aplicación no interpreta la ley. |
| Número de protocolo | **no, lo escribe la notaria** | Un número mal puesto es un problema serio. |
| Documentos tributarios electronicos | **no, y no habra** | La API del kit es de solo lectura. |