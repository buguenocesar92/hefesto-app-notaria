# 08 - Respaldos

## 1. Que hay que respaldar

| Que | Donde | Que se pierde si no |
|---|---|---|
| **Base de datos** | Volumen `postgres-datos` | Los expedientes, las partes, los estados, el historial. |
| **Documentos** | Volumen `documentos` | **El historial documental.** Que no se puede reconstruir. |
| **Configuración** | `.env.prod`, fuera del compose | El secreto de cifrado y las credenciales. |
| **Certificados propios** | Si los hay | La identidad criptografica del dominio. |

### Por que los documentos son el respaldo critico

Un expediente sin sus documentos **no vale nada**. Si la base sobrevive y el
volumen de documentos no, queda una lista de expedientes que nadie puede
abrir. Y si el volumen sobrevive y la base no, quedan archivos sueltos sin
saber a que expediente pertenecen ni si estaban vigentes.

Las dos cosas se respaldan **juntas, y se restauran juntas**.

---

## 2. El respaldo de la base

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    exec -T postgres pg_dump \
      --username="$DB_USERNAME" \
      --dbname="$DB_DATABASE" \
      --format=custom \
      --compress=9 \
      --no-owner \
      > respaldo-$(date +%Y%m%d-%H%M).dump
```

### Opciones que no son negociables

| Opcion | Por que |
|---|---|
| `--format=custom` | Permite restaurar tabla por tabla y en paralelo. |
| `--no-owner` | El respaldo restaura en cualquier instalación, no solo en una donde exista el mismo rol. |
| `--no-owner` **y el `--clean`** al restaurar | Para que un objeto que ya no existe no sobreviva a la restauración. |

### Que hay que Excluir

**Ninguna.** Se respalda la base completa, incluidas `bitacora_accesos` y
`alertas_semaforo`.

El aislamiento por `tenant_id` no se necesita para respaldar: `pg_dump` es un
respaldo administrativo, se ejecuta con el rol dueño.

---

## 3. El respaldo de los documentos

Los documentos **no estan en la base**: estan en el volumen. Es una decisión
deliberada: un respaldo de la base con 50.000 PDF adentro tarda horas y no se
puede verificar facilmente.

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    run --rm --no-deps api \
    tar czf - -C "$(docker volume inspect notaria_documentos --format '{{ .Mountpoint }}')" .
```

### Como verificar que el respaldo esta completo

Con `hash_sha256`, que **cada versión de documento** tiene en
`documento_versiones`.

El hash detecta corrupcion y cambios accidentales. No es una firma
criptografica, y no pretende serlo: sirve para responder "el archivo que tengo
hoy es el mismo que se respaldo el martes".

---

## 4. LA REGLA DEL RESPALDO QUE NO SE CUMPLE

> **Un respaldo que nunca se restauro no es un respaldo. Es una carpeta con
> la esperanza de que sirva.**

Por eso hay que **restaurar al menos una vez**, en un entorno aparte, y
comprobar:

1. Que la base levanta y las migraciones estan aplicadas.
2. Que `notaria:verificar-aislamiento` pasa.
3. Que un expediente se abre y muestra sus etapas.
4. Que sus documentos se descargan y **el hash coincide** con el que estaba en
   la base.
5. Que el semaforo corre.

Si algo de eso falla, el respaldo no sirve y hay que arreglarlo **antes** de
tener un problema de verdad.

---

## 5. Frecuencia y retencion

| Que | Cada cuanto | Cuanto se guarda |
|---|---|---|
| Base de datos | **Diario** | 30 dias |
| Documentos | **Diario** | 30 dias |
| Antes de cualquier migración | **Siempre** | Hasta que la versión nueva lleve un tiempo en producción |
| Semanal (lunes) | **Semanal** | 12 meses |

La copia del **lunes** es la que se guarda mas tiempo, porque es la que cubre
un incidente de la semana anterior.

La copia previa a migración es la que evita el peor escenario posible: un
despliegue que deja los expedientes incompletos, y una base que ya no se puede
leer con la versión nueva del código.

---

## 6. Donde se guardan

### La regla

**El respaldo no puede estar en la misma maquina que lo que respalda.**

Si la maquina se muere, se llevan los datos **y** el respaldo. Un respaldo en
el mismo disco protege contra errores de verdad (una tabla vaciada, un `DROP
`), y contra nada mas.

### Opciones

| Opcion | Comentario |
|---|---|
| Almacenamiento de objetos (S3 y similares) | Lo habitual. Cifrado en el destino. |
| Unidad externa, en otra ubicacion fisica | Aceptable, si esta en otro edificio. |
| Otro servidor | Aceptable. |

Y en todos los casos: **cifrado en reposo** y **acceso restringido**, porque
un respaldo de una notaria tiene todos los datos que la notaria tiene.

---

## 7. Restauración

### Práctica

```bash
# 1. Crear una base vacia
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    exec -T postgres createdb -U "$DB_USERNAME" notaria_restauracion

# 2. Restaurar
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    exec -T postgres pg_restore \
      --username="$DB_USERNAME" \
      --dbname=notaria_restauracion \
      --clean --if-exists --no-owner \
      < respaldo-20261017-030000.dump

# 3. Verificar el aislamiento
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    exec -T api php artisan notaria:verificar-aislamiento
```

### Si se restaura para probar

Si se restaura un respaldo para **probarlo en otro lado**, la copia va a
contener los datos reales de la notaria. Ese entorno de prueba tiene que estar
tratandose como el original: acceso restringido, y eliminado al terminar.

---

## 8. Lo que este sistema NO hace

Con la misma honestidad que en el resto del documento:

- **No hay respaldos automaticos incluidos en el repositorio.** El compose no
  define ningun servicio de respaldo. Hay que configurarlo en la
  infraestructura donde corra la aplicación, y probarlo.
- **No hay verificación automática de que un respaldo se pueda restaurar.** Es
  un proceso manual, y manual es exactamente lo que se salta.
- **No hay cifrado de los respaldos.** Es responsabilidad del destino.
- **No hay política de retencion implementada.** Cuanto tiempo se conservan los
  expedientes de una notaria que deja de usar el sistema es una decisión del
  cliente. Este sistema no la implementa y no la puede implementar: no conoce
  la normativa aplicable ni el acuerdo comercial.

---

## 9. Checklist de respaldo

- [ ] `pg_dump` diario funcionando, con la base **completa**.
- [ ] Volumen de documentos respaldado a diario.
- [ ] Respaldo **fuera** de la maquina que guarda los datos.
- [ ] Respaldo **cifrado** en reposo.
- [ ] Respaldo previo a migraciones, siempre.
- [ ] **Restauración probada al menos una vez**, con verificación de hashes.
- [ ] Copia del lunes guardada 12 meses.
- [ ] Que alguien sepa **donde estan** los respaldos y **como se restauran**,
      sin depender de la persona que los hizo.
- [ ] Que la persona que rota lo sepa. En serio: es el punto de falla mas
      comun de los respaldos.