import type { ApiError, Sesion } from '~/tipos/api';

/**
 * Sesion y llamadas a la API.
 *
 * TODAS LAS PETICIONES PASAN POR AQUI
 * ----------------------------------
 * Nunca se llama a la API de Laravel directamente desde el navegador: todo
 * pasa por el BFF de `/api/**` (ver `server/api/[...ruta].ts`). Por eso las
 * llamadas van a rutas relativas, no a `config.apiBase`.
 *
 * El token CSRF viaja en la cookie `XSRF-TOKEN` y lo agrega el BFF. El
 * componente de Nuxt no tiene que hacer nada.
 */
export function useSesion() {
  const sesion = useState<Sesion | null>('sesion', () => null);

  async function cargar(): Promise<Sesion | null> {
    try {
      const respuesta = await $fetch<Sesion>('/api/v1/auth/notarias');
      // El endpoint de entrada no trae la sesion: si no hay sesion, el
      // servidor responde 401 y `useFetch` deja `sesion` en null.
      sesion.value = respuesta ?? null;
    } catch {
      sesion.value = null;
    }

    return sesion.value;
  }

  return {
    sesion,
    cargar,
    entrar: (slug: string) => $fetch<{ redirigir_a: string }>(`/api/v1/auth/entrar/${slug}`),
    salir: () => $fetch('/api/v1/auth/salir', { method: 'POST' }),
  };
}

/**
 * Error de la API con su mensaje en espanol.
 *
 * La API responde `{ mensaje, errores }`. Si no se puede leer, se muestra un
 * texto generico: nunca el error crudo de JavaScript, que puede contener
 * detalles internos de la base o del servidor.
 */
export function mensajeDeError(error: unknown): string {
  if (typeof error === 'object' && error !== null) {
    const api = error as ApiError;

    if (api.mensaje) {
      return api.mensaje;
    }

    if (api.errores) {
      const primero = Object.values(api.errores)[0];

      if (primero && primero[0]) {
        return primero[0];
      }
    }
  }

  return 'No se pudo completar la operacion.';
}

/** Formatea una fecha en el formato chileno habitual, sin hora. */
export function fechaCorta(valor: string | null): string {
  if (!valor) {
    return '-';
  }

  return new Date(valor).toLocaleDateString('es-CL', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}