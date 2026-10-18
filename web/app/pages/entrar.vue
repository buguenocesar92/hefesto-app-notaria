<template>
  <main class="entrada">
    <section class="tarjeta">
      <h1>Gestion de Notarias</h1>
      <p class="intro">
        Ingrese con la cuenta que le creo su notaria.
      </p>

      <!--
        El aviso de que esto NO es un sistema legal, va en la pantalla de
        entrada, no escondido en una ayuda. Quien entra es una persona de una
        oficina del Estado: tiene que saber desde el primer momento que la
        herramienta no dice que algo sea valido.
      -->
      <p class="advertencia">
        Esta aplicacion organiza y registra expedientes, etapas, antecedentes y documentos.
        <strong>No reemplaza ni interpreta la ley</strong>, y no dice si un tramite es valido.
        Los dias que muestra son los que la notaria configuro como alarma interna.
      </p>

      <p v-if="error" class="error">{{ error }}</p>

      <label for="notaria">Notaria</label>
      <select id="notaria" v-model="seleccionada" class="selector">
        <option v-for="notaria in notarias" :key="notaria.slug" :value="notaria.slug">
          {{ notaria.nombre }}
        </option>
      </select>

      <button
        class="boton"
        type="button"
        :disabled="!seleccionada || enviando"
        @click="ingresar"
      >
        {{ enviando ? 'Redirigiendo...' : 'Ingresar' }}
      </button>
    </section>
  </main>
</template>

<script setup lang="ts">
import type { Sesion } from '~/tipos/api';

definePageMeta({
  layout: 'default',
});

useHead({ title: 'Ingresar · Gestion de Notarias' });

/**
 * Pantalla de entrada.
 *
 * La lista de notarias sale del endpoint publico, que devuelve SOLO slug y
 * nombre. No se reciben RUT, direccion, correo ni telefono: antes del login no
 * hay motivo para que el navegador vea esos datos.
 */
const ruta = useRoute();
const error = ref<string | null>(
  typeof ruta.query.error === 'string' ? ruta.query.error : null,
);

const seleccionada = ref<string>('');
const enviando = ref(false);

const { data } = await useFetch<{ notarias: Array<{ slug: string; nombre: string }> }>(
  '/api/v1/auth/notarias',
);

const notarias = computed(() => data.value?.notarias ?? []);

onMounted(() => {
  if (notarias.value.length > 0) {
    seleccionada.value = notarias.value[0]?.slug ?? '';
  }
});

async function ingresar(): Promise<void> {
  if (!seleccionada.value) {
    return;
  }

  enviando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{ redirigir_a: string }>(
      `/api/v1/auth/entrar/${seleccionada.value}`,
    );

    await navigateTo(respuesta.redirigir_a, { external: true });
  } catch (fallo) {
    error.value = mensajeDeError(fallo);
    enviando.value = false;
  }
}
</script>

<style scoped>
.entrada {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.tarjeta {
  width: 100%;
  max-width: 26rem;
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  padding: 2rem;
}

h1 {
  font-size: 1.375rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.intro {
  color: #6b7280;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.advertencia {
  font-size: 0.8125rem;
  color: #4b5563;
  background-color: #f9fafb;
  border-left: 3px solid #9ca3af;
  padding: 0.75rem 1rem;
  border-radius: 0.25rem;
  margin-bottom: 1.25rem;
}

.error {
  color: #b91c1c;
  font-size: 0.875rem;
}

label {
  display: block;
  font-size: 0.8125rem;
  color: #374151;
  margin-bottom: 0.25rem;
}

.selector {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
  background-color: #ffffff;
}

.boton {
  width: 100%;
  padding: 0.625rem;
  background-color: #111827;
  color: #ffffff;
  border: none;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  cursor: pointer;
}

.boton:disabled {
  background-color: #9ca3af;
  cursor: not-allowed;
}
</style>