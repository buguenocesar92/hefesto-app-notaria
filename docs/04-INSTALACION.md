# 04 - Instalación

## ADVERTENCIA PRIMERO

Esto describe como **levantar el sistema para desarrollo**. No es una guia
para ponerlo en producción en una notaria real, y este software **no esta
listo para eso**. Ver el README.

En el entorno donde se escribio el código **no habia PHP, ni Composer, ni
Docker**: nada de lo que se describe aca se ejecuto. Ver
[10](10-PRUEBAS-Y-ESTADO.md).

---

## 1. Requisitos

| Herramienta | Versión | Para que |
|---|---|---|
| Docker | 24 o superior | Corre todo en contenedores. |
| Docker Compose | v2 | Levanta los servicios. |
| Node.js | 20.19 o superior | Solo si se corre la interfaz fuera de Docker. |
| PHP | 8.3 | Solo si se corre la API fuera de Docker. |
| Composer | 2 | Solo si se corre la API fuera de Docker. |

Todas las versiones de imagen van **fijas** en los compose:

| Servicio | Versión fijada |
|---|---|
| PostgreSQL | `18.6` |
| Redis | `8.8.3-alpine` |
| Caddy | `2.11.7-alpine` |
| Authentik | `2025.10` |
| PHP | `8.3.29-fpm-bookworm` |
| Node | `22.21.1-bookworm-slim` |

Una imagen que se actualiza sola un miercoles a las cuatro de la tarde es una
forma de tener un incidente sin haber tocado nada.

---

## 2. Levantar con Docker

### 2.1 Preparar el `.env`

```bash
cd apps/notaria
cp .env.example .env
```

Generar los secretos:

```bash
openssl rand -base64 48                  # APP_KEY
openssl rand -base64 60 | tr -d '\n'     # AUTHENTIK_SECRET_KEY
openssl rand -hex 32                     # AUTHENTIK_BOOTSTRAP__TOKEN
```

Pegarlos en `.env`. **Ningun valor de `.env.example` es real**, y `.env` esta
en `.gitignore`.

### 2.2 Levantar

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env up -d
```

| Servicio | Direccion |
|---|---|
| Interfaz | http://localhost:3000 |
| API | http://localhost:8000 |
| PostgreSQL | localhost:5432 |
| Authentik | http://localhost:9000 |

### 2.3 Migraciones y datos de ejemplo

El compose de desarrollo corre las migraciones solo
(`NOTARIA_CORRER_MIGRACIONES=true`). Para cargar los datos de ejemplo:

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env exec api \
    php artisan db:seed
```

La semilla crea **dos** notarias de ejemplo, a propósito: con una sola no se
puede comprobar nada del aislamiento.

---

## 3. Correr sin Docker

### 3.1 La API

```bash
cd apps/notaria/api
composer install
cp .env.example .env
php artisan key:generate
```

Crear el rol de base de datos **sin superusuario y sin BYPASSRLS**:

```sql
CREATE ROLE notaria_app LOGIN PASSWORD '...' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
CREATE DATABASE notaria OWNER notaria_app;
```

> Si el rol es superusuario o tiene `BYPASSRLS`, PostgreSQL **ignora todas
> las políticas RLS** y una notaria ve los expedientes de otra. El comando
> `notaria:verificar-aislamiento` lo comprueba.

```bash
php artisan migrate
php artisan db:seed
php artisan serve
```

### 3.2 La interfaz

```bash
cd apps/notaria/web
npm install
cp .env.example .env
npm run dev
```

---

## 4. Configurar Authentik

1. Entrar a `http://localhost:9000` con las credenciales de bootstrap.
2. Crear un proveedor **OAuth2 / OpenID Connect**.
3. Anotar el **client id** y el **client secret**.
4. En *Redirect URIs* poner **exactamente**:
   `http://localhost:8000/api/v1/auth/callback`
5. En *Scopes*, agregar `openid`, `profile` y `email`.
6. Copiar el emisor (issuer), que tiene esta forma:
   `https://id.ejemplo.cl/application/o/notaria/`
7. Pegar todo en `api/.env`.

### El grupo de Authentik

Si se quiere usar un grupo de Authentik para el primer alta, se configura
`AUTHENTIK_GRUPO_ADMIN`.

**Aclaracion importante:** ese grupo solo sirve para el primer alta. De ahi en
adelante, el permiso lo da `usuarios.rol`, que es lo que la notaria controla
desde la aplicación. Ver [03](03-MULTITENANCY-Y-ROLES.md), seccion "Roles".

---

## 5. Dar de alta una notaria

### 5.1 La notaria

La tabla `notarias` no tiene RLS, así que se puede escribir directamente:

```sql
INSERT INTO notarias (id, slug, nombre, activo, created_at, updated_at)
VALUES (
    gen_random_uuid(),
    'notaria-ejemplo',
    'Notaria de Ejemplo',
    true,
    now(), now()
);
```

El `slug` es lo que se usa en la URL de entrada.

### 5.2 La configuración

**Sin umbrales.** Se deja así a propósito: el semaforo arranca apagado y el
tablero pide configurar el umbral. Es el comportamiento honesto. Ver
[05](05-CONFIGURACION.md).

### 5.3 El usuario

La identidad vive en Authentik. El `authentik_sub` es el identificador del
sujeto en Authentik, y tiene que coincidir **exactamente** con el `sub` del
token:

```sql
INSERT INTO usuarios (
    id, tenant_id, authentik_sub, nombre, apellidos, correo, rol, activo, created_at, updated_at
) VALUES (
    gen_random_uuid(),
    '<id-de-la-notaria>',
    '<sub-de-authentik>',
    'Ana', 'Perez', 'ana.perez@ejemplo.cl',
    'administrador', true,
    now(), now()
);
```

Para obtener el `sub`: entrar por el flujo normal y mirar el token de
identacion en `https://id.ejemplo.cl/application/o/notaria/>.well-known/jwks/`,
o usar la herramienta de decodificacion de Authentik.

---

## 6. Comprobar que todo funciona

En este orden.

### 6.1 La base responde

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env exec api \
    php artisan notaria:verificar-aislamiento
```

**Este comando tiene que decir "Aislamiento entre notarias verificado
correctamente" antes de seguir.** Si no lo dice, el sistema esta abierto entre
notarias: ver [03](03-MULTITENANCY-Y-ROLES.md), seccion 5.

### 6.2 La API responde

```bash
curl -s http://localhost:8000/api/v1/salud
```

### 6.3 El semaforo corre

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env exec api \
    php artisan notaria:revisar-semaforo
```

### 6.4 Comprobar el aislamiento A MANO

Esto es importante y conviene hacerlo una vez, porque es la forma mas
barata de confirmar que la RLS esta puesta:

1. Entrar como persona de la **Notaria de Ejemplo Palma**.
2. Abrir un expediente y anotar su identificador.
3. Entrar como persona de la **Notaria de Ejemplo Sur**.
4. Intentar abrir ese identificador.
5. **Tiene que dar 404.** Si da 200, el aislamiento NO esta funcionando, y
   hay que parar y revisarlo.

---

## 7. Datos de desarrollo

La semilla crea dos notarias con datos de ejemplo:

| Notaria | Que tiene |
|---|---|
| **Notaria de Ejemplo Palma** | Un expediente **parado**: una etapa lleva 18 dias esperando un antecedente que nadie fue a buscar. Es el caso que el producto viene a resolver. |
| **Notaria de Ejemplo Sur** | Lo mismo, para comprobar el aislamiento a mano. |

### Advertencias sobre los datos de ejemplo

1. **Los `authentik_sub` son ficticios.** Las personas de ejemplo **no pueden
   entrar** hasta que sus identidades existan en Authentik con ese mismo
   `sub`, o hasta que se borre la semilla.

2. **Los tipos de operación y las etapas son inventados.** "Ejemplo:
   compraventa", "Ejemplo: testamento" y sus etapas existen para que haya algo
   en la pantalla. **No son el procedimiento de ninguna operación real**, y
   una notaria no debe copiarlos. Cada una define los suyos.

3. **Los números de protocolo son inventados.** En la vida real los escribe
   la notaria.

4. **Los umbrales por defecto arrancan en `NULL`**, a propósito. Con eso el
   semaforo no marca nada hasta que la notaria defina su criterio.

---

## 8. Problemas frecuentes

| Sintoma | Causa probable |
|---|---|
| `No hay contexto de notaria en la conexion` | Una consulta se ejecuto fuera del middleware `ResolverTenant`. Es el aislamiento fallando cerrado: hay que revisar que operación se escapo. |
| El tablero sale vacio y con aviso | La notaria no configuro el umbral. **No es un error.** Ver [05](05-CONFIGURACION.md). |
| `Las pruebas pasan pero no filtran nada` | Se estan corriendo contra SQLite. Las pruebas de aislamiento **requieren PostgreSQL**. |
| 401 en todas las peticiones | La cookie de sesión no viaja. Revisar `SESSION_SECURE_COOKIE` y que se este entrando por HTTPS. |
| Faltan dependencias de PHP | `docker compose build api --no-cache` |