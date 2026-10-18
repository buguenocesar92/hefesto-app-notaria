/**
 * Exige sesion antes de entrar a una pantalla.
 *
 * Si no hay sesion, va a la pantalla de entrada. Si la hay pero la API la
 * rechazo (la cuenta se desactivo mientras la persona tenia la pantalla
 * abierta), tambien.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path === '/entrar') {
    return;
  }

  try {
    await $fetch('/api/v1/tabero', {
      // Una peticion minima para comprobar la sesion. No se guardan los datos:
    });
  } catch {
    return navigateTo({ path: '/entrar', query: { desde: to.fullPath } });
  }
});