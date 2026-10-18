# 10 - Pruebas y estado

## 1. ESTADO GENERAL: CÓDIGO ESCRITO, SIN EJECUTAR

En el entorno donde se escribio esta aplicación **no habia PHP, ni Composer,
ni Docker, ni PostgreSQL**:

```
$ php --version
php: NO ENCONTRADO

$ composer --version
composer: NO ENCONTRADO

$ docker --version
docker: NO ENCONTRADO
```

**No se ejecuto ni una linea de este código.** Ni las pruebas, ni las
migraciones, ni una pagina, ni un comando de consola.

Lo que si se hizo de verdad esta en la seccion 4.

Nada de lo que esta escrito aca como "probado" lo esta, salvo que diga lo
contrario de forma explicita. **Lo único que hay son pruebas escritas.**

---

## 2. Las pruebas que existen

### 2.1 Aislamiento entre notarias

`api/tests/Feature/Aislamiento/` — **requiere PostgreSQL**.

| Prueba | Que comprueba |
|---|---|
| `test_sin_contexto_de_notaria_la_consulta_falla_en_vez_de_traer_datos` | Sin contexto, la consulta **falla**, no devuelve cero filas. |
| `test_cada_notaria_solo_ve_sus_propios_expedientes` | Cada notaria ve solo los suyos. |
| `test_el_expediente_de_otra_notaria_no_se_puede_leer_ni_escribir` | No se lee ni se escribe. |
| **`test_un_expediente_no_ve_los_documentos_de_otra_notaria`** | **El caso pedido: un expediente no ve los documentos de otro.** |
| `test_no_se_puede_insertar_una_fila_con_el_tenant_de_otra_notaria` | La política `WITH CHECK` impide escribir en nombre de otra. |
| `test_las_partes_de_otra_notaria_no_se_ven` | Las partes también. |
| `test_las_etapas_y_antecedentes_de_otra_notaria_no_se_ven` | Y las etapas. |
| `test_el_contexto_de_notaria_no_sobrevive_a_la_transaccion` | El alcance de transacción funciona. |
| `test_la_funcion_de_contexto_falla_cerrada_sin_notaria` | Falla cerrado. |

Y en `AislamientoEnEndpointsTest.php`, que comprueba que la API responde
**`404` y no `403`**, y que el tablero no cuenta lo de otra notaria.

### 2.2 El semaforo

`api/tests/Unit/SemaforoEtapaTest.php` — **no necesita base de datos**.

| Prueba | Que comprueba |
|---|---|
| `test_sin_umbral_configurado_no_se_marca_nada` | 500 dias esperando y **no se marca**. |
| `test_dentro_del_umbral_es_verde` | Verde bajo el umbral. |
| `test_al_llegar_al_umbral_pasa_a_amarillo` | Amarillo al cruzar N. |
| `test_al_llegar_al_critico_pasa_a_rojo` | Rojo al cruzar M. |
| `test_sin_umbral_critico_no_hay_rojo_nunca` | Sin M, no hay rojo. |
| `test_una_etapa_terminada_nunca_se_marca_como_atrasada` | Terminada hace 90 dias no es atraso. |
| `test_sin_fecha_de_referencia_no_se_inventan_dias` | Sin fecha, cero dias. |
| `test_los_dias_son_completos_no_redondeados` | 23 horas no son un dia. |
| `test_una_fecha_en_el_futuro_no_produce_dias_negativos` | Reloj desfasado no rompe el calculo. |
| `test_un_critico_menor_que_la_alerta_no_produce_rojo_sorpresivo` | Configuración erronea se contiene. |
| **`test_el_resumen_no_afirma_ningun_vencimiento`** | **El texto jamas dice "vencio" ni "plazo legal".** |
| `test_sin_umbral_el_resumen_explica_que_no_se_marca_y_por_que` | Explica la falta de umbral. |

`api/tests/Feature/Semaforo/EtapasAtrasadasTest.php` — **requiere
PostgreSQL**.

Comprueba el ciclo completo: que una etapa se marque sola, que correr el
semaforo tres veces **no duplique** la alerta, que la alerta se cierre cuando
la etapa se resuelve, y que se abra una nueva si vuelve a atrasarse.

### 2.3 Documentos: nunca se borran

`api/tests/Feature/Documentos/DocumentosNoSeBorranTest.php` — **requiere
PostgreSQL**.

| Prueba | Que comprueba |
|---|---|
| `test_agregar_una_version_no_borra_la_anterior` | La versión anterior sigue existiendo. |
| `test_marcar_como_reemplazado_conserva_el_documento` | El registro queda. |
| **`test_la_api_no_tiene_ninguna_ruta_delete_de_documentos`** | **Falla si alguien agrega un `DELETE`.** |
| `test_la_api_no_tiene_ninguna_ruta_delete_de_expedientes` | Tampoco de expedientes. |
| `test_el_listado_incluye_los_documentos_reemplazados` | El historial va completo. |

---

## 3. Por que las pruebas de aislamiento necesitan PostgreSQL

Porque el aislamiento esta implementado con **Row Level Security**, que es
una función de PostgreSQL.

Contra SQLite, estas pruebas **pasarian sin comprobar nada**: no hay RLS, las
políticas no existen, y un `tenant_id` equivocado devolveria filas igual. Un
"todo verde" en SQLite no dice absolutamente nada sobre el aislamiento.

Por eso `phpunit.xml` fija `DB_CONNECTION=pgsql` de forma explicita, con el
motivo escrito al lado.

---

## 4. Lo que SI se ejecuto de verdad

Con las herramientas que si habia en el entorno:

| Que | Resultado |
|---|---|
| `bash -n` sobre los scripts `.sh` | **Pasan.** El único script es `deploy/api/docker/entrypoint.sh`. |
| Lectura del patron de multitenancy de referencia | Hecho, y reutilizado. |
| Busqueda de caracteres CJK y cirilicos en todos los archivos | **Ejecutada en dos pasadas**, con correcciones. Ver seccion 5. |
| Verificación de que todo queda dentro de `apps/notaria/` | **Ejecutada.** Un archivo se escribio por error fuera de la ruta y se corrigio. |

Y nada mas. **No se ejecuto PHP, ni SQL, ni JavaScript.**

---

## 5. La revision de idioma

Se hizo una pasada completa buscando caracteres **CJK** y **cirilicos**, que
se colaron al escribir código y documentacion en un teclado que no es el
correcto.

Se encontraron y corrigieron, entre otros. Se describen sin copiar los
caracteres raros, para que este documento también pase limpio:

| Donde | Que se habia colado |
|---|---|
| `docs/01` | Dos caracteres CJK en medio de una frase sobre antecedentes. |
| `docs/02` | Dos caracteres CJK entre "la" y "actualizaron". |
| `deploy/api/docker/php.ini` | Dos caracteres cirilicos en un comentario sobre `SameSite`. |
| `docs/06` | Palabras pegadas: "el sistema" + "espidiendole". |
| `docs/07` | Separacion de palabras rota en una frase sobre sucesiones. |

También se corrigieron palabras en ingles que se colaron en el texto
(`Giving`, `internally`, `worse`, `Requires`, `carry`, `knows`, `asking`,
`loved ones`, `softwre`) y errores de tipeo como `evictidad`, `esispidiendo`
y `poundsados`.

---

## 6. Lo que NO se sabe, y hay que probar

Esta es la parte que importa. **Nada de lo siguiente se comprobo.**

### 6.1 El aislamiento

**Lo mas importante.** Todas las pruebas de RLS estan escritas y **ninguna se
corrio**.

No se sabe si:
- las políticas se crean correctamente;
- el rol de la base es el correcto;
- `FORCE ROW LEVEL SECURITY` se aplica como se espera;
- el contexto de transacción realmente se descarta;
- hay alguna tabla sin proteger.

**La migración verifica esto al aplicarse** (falla si alguna tabla no tiene
`tenant_id`), pero **no se ejecuto**.

**Como probarlo:**

```bash
createdb notaria_test
vendor/bin/phpunit --testsuite=Aislamiento
php artisan notaria:verificar-aislamiento
```

Si el comando dice que el aislamiento **no** esta bien puesto, hay que parar
y corregirlo antes de seguir.

### 6.2 Las migraciones

21 migraciones escritas, **ninguna ejecutada**.

Lo mas probable es que alguna tenga un error de sintaxis SQL, o un tipo de
columna que PostgreSQL no acepte. Es lo primero que hay que correr.

### 6.3 El código de la API

- Que los `use` esten completos y correctos: se escribieron muchos modelos,
  controladores y servicios, y es facil que falte uno.
- Que los nombres de los metodos entre clases coincidan.
- Que los tipos de las columnas coincidan con los `casts`.
- Que las rutas de descarga de documentos funcionen con el nombre real de la
  ruta.

### 6.4 El semaforo en ejecución

La lógica esta probada en la unidad (`SemaforoEtapaTest`), pero **no se probo**
que el recorrido completo sobre la base funcione: el `chunkById`, la escritura
de alertas, el indice único parcial, el cierre de alertas.

### 6.5 La interfaz

Nuxt esta escrito y **no se compilo ni se ejecuto nunca**. No se sabe si
compila, ni si los tipos del composable calzan con lo que devuelve la API.

### 6.6 Docker

Los dos `docker-compose.yml` y los dos Dockerfile estan escritos y **no se
construyeron**. El `docker-compose.dev.yml` se reviso a mano (el contexto de
compilacion era `..`, no `../api`), pero el resto no se probo.

### 6.7 La autenticación

El flujo OIDC contra Authentik esta escrito y **nunca se ejecuto**. Es la parte
mas probable de necesitar ajustes: el descubrimiento del proveedor, la
verificación del token y la firma del estado tienen detalles que solo se
descubren corriendo.

---

## 7. Riesgos, ordenados por gravedad

### 7.1 CRITICO: una fuga entre notarias

Si la RLS no queda bien puesta, una notaria ve los expedientes de otra. Eso
significa **datos de patrimonio, familia y sucesiones de personas
identificables**, protegidos por la Ley 19.628.

Mitigaciones escritas:
- `WITH CHECK` y `FORCE ROW LEVEL SECURITY`.
- Contexto con alcance de transacción.
- La función de contexto **falla cerrado**.
- 17 tablas protegidas y verificadas por la migración.
- `notaria:verificar-aislamiento` en el despliegue.
- Pruebas escritas (no ejecutadas).

**Mitigacion que falta: ninguna.** Hasta que las pruebas no se corran contra
PostgreSQL, esto es un riesgo abierto.

### 7.2 CRITICO: que el semaforo se lea como un plazo legal

Es el riesgo mas probable **en el uso diario**, y no tiene que ver con bugs.

Una notaria ve un tablero con etapas rojas. Alguien lo lee como "esto se
vencio". Alguien, en una situacion real, mueve plazos de una persona en
función de ese color.

Mitigaciones escritas:
- Sin umbral configurado, **no se marca nada**.
- Los textos nunca dicen "vence" ni "plazo legal" (con una prueba que lo
  vigila).
- La advertencia aparece en la pantalla de tablero, en la de configuración y
  en la de entrada.

**Lo que no se puede evitar:** la primera vez que alguien ve un tablero rojo,
va a preguntar "esto vence?". La única mitigacion real es explicar, y por eso las
advertencias estan en tres pantallas.

### 7.3 ALTO: un registro mal asentado

Un expediente notarial es un asiento. Si el sistema deja un documento
marcado como vigente cuando no lo es, o una etapa cerrada que no se cerro, la
consecuencia no se corrige: no hay forma de deshacer un asiento mal hecho.

Mitigaciones escritas:
- Los documentos no se borran, se reemplazan.
- El estado del expediente **se deriva** de las etapas.
- El número de protocolo lo escribe la notaria.

**Lo que falta:** nada en el código, pero **falta toda la revision humana** que
una aplicación de este tipo necesita antes de tocar expedientes reales.

### 7.4 ALTO: el volumen de documentos no esta cifrado en reposo

Si el disco del servidor se compromete, los PDF se leen. Es una decisión de
infraestructura, no de esta aplicación, pero **alguien tiene que tomarla**.

### 7.5 MEDIO: los tipos de operación de la semilla son inventados

Si alguien copia los tipos de ejemplo ("Ejemplo: compraventa") creyendo que son
los reales, tendria una notaria trabajando con un tramite equivocado.

Mitigaciones: marcados como ejemplo en el nombre, en la descripcion y en
todos los documentos que los mencionan.

### 7.6 MEDIO: ninguna prueba se ejecuto

Todo lo anterior se reduce a esto. Un sistema escrito y no ejecutado tiene
errores; la mayoria se ven rapido al correrlo, y algunos no se ven nunca.

---

## 8. Orden recomendado para poner esto a prueba

1. **`bash -n` en los scripts.** Ya pasa, pero se repite al cambiar algo.
2. **Revisar los `use` y los nombres de metodo a mano.** Es lo mas probable
   que falle al compilar.
3. **Levantar PostgreSQL y correr las migraciones.** Corregir lo que falle.
4. **`notaria:verificar-aislamiento`.** Si falla, **parar**. No seguir hasta
   que pase.
5. **Correr la suite de aislamiento.**
   `vendor/bin/phpunit --testsuite=Aislamiento`
6. **Correr la suite del semaforo.**
   `vendor/bin/phpunit --testsuite=Semaforo`
7. **Correr las pruebas de documentos.**
8. **Correr la suite de unidad.** No necesita base de datos; se puede hacer en
   cualquier parte.
9. **Levantar la interfaz** y ver que compila y que carga el tablero.
10. **Probar el login contra Authentik**, que es la parte con mas detalle fino.
11. **Recien ahi**, revisar el aislamiento a mano entre las dos notarias de
    ejemplo, como se describe en [04](04-INSTALACION.md), seccion 6.4.
12. **Recien despues de todo eso**, pensar en una notaria real. Y con alguien
    que haya leido el README.

---

## 9. La conclusion honesta

Esta aplicación es **código escrito, sin ejecutar**.

Tiene:

- un modelo de datos pensado para el problema real;
- un semaforo con una regla que resiste el caso dificil (sin umbral, no
  marca nada);
- un aislamiento reutilizado de un patron ya decidido, no inventado;
- pruebas escritas para lo que mas importa;
- documentacion que dice lo que no sabe, en vez de disimularlo.

**No tiene:**

- ni una prueba ejecutada;
- ni una migración aplicada;
- ni una linea compilada;
- ninguna revision de seguridad;
- ninguna prueba de penetracion;
- ningun respaldo probado.

El camino de un sistema escrito a un sistema en el que se puede confiar un
expediente real es largo, y este esta en su primer tramo. La parte mas
importante del trabajo de hoy no es el código: es haber dejado escrito, en
letra grande y en varios lugares, **que esto no sabe interpretar la ley, y
que por eso todo dato legal es configurable y viene vacio**.

Esa limitacion no se resuelve escribiendo mas código. Se resuelve confirmando
cada dato con un notario, uno por uno.

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`notaria_admin`) y el rol de la
aplicación (`notaria_app`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `notaria_app` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS.
3. Correr `php artisan notaria:verificar-aislamiento` y confirmar que sale con
   código 0.
4. Probar el escenario que motiva todo el cambio: una notaría intentando leer
   un expediente de otra.

Este es el punto 4 del orden recomendado de la sección 8. No conviene saltárselo:
en una notaría, un fallo de aislamiento no es una molestia.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.