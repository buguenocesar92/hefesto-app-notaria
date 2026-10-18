<template>
  <main class="pagina">
    <h1>Expedientes</h1>

    <section class="filtros">
      <input
        v-model="buscar"
        type="search"
        placeholder="Buscar por folio, numero de protocolo o descripcion"
        class="buscador"
      >
    </section>

    <p v-if="error" class="error">{{ error }}</p>

    <table v-if="datos && datos.data.length > 0" class="tabla">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Tipo de operacion</th>
          <th>Estado</th>
          <th>Semaforo</th>
          <th>Inicio</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="expediente in datos.data" :key="expediente.id">
          <td>
            <NuxtLink :to="`/expedientes/${expediente.id}`">
              {{ expediente.folio }}
            </NuxtLink>
            <span v-if="expediente.numero_protocolo" class="secundario">
              {{ expediente.numero_protocolo }}
            </span>
          </td>
          <td>{{ expediente.tipo_operacion?.nombre ?? '-' }}</td>
          <td>{{ expediente.estado_etiqueta }}</td>
          <td>
            <IndicadorSemaforo
              :nivel="expediente.nivel"
              :etiqueta="expediente.nivel_etiqueta"
            />
          </td>
          <td>{{ fechaCorta(expediente.fecha_inicio) }}</td>
        </tr>
      </tbody>
    </table>

    <p v-else-if="datos" class="vacio">
      No hay expedientes que coincidan con la busqueda.
    </p>
  </main>
</template>

<script setup lang="ts">
import type { Expediente } from '~/tipos/api';

definePageMeta({
  middleware: 'auth',
});

useHead({ title: 'Expedientes · Gestion de Notarias' });

const error = ref<string | null>(null);
const buscar = ref('');
const pagina = ref(1);

const { data, refresh } = await useFetch<{
  data: Expediente[];
  total: number;
}>('/api/v1/expedientes', {
  query: computed(() => ({
    pagina: pagina.value,
    buscar: buscar.value || undefined,
  })),
});

/** Cada vez que cambia el texto, se vuelve a la primera pagina. */
watch(buscar, () => {
  pagina.value = 1;
});

async function recargar(): Promise<void> {
  try {
    await refresh();
    error.value = null;
  } catch (fallo) {
    error.value = mensajeDeError(fallo);
  }
}

watch([buscar, pagina], recargar);
</script>

<style scoped>
.pagina {
  max-width: 64rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 4rem;
}

h1 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.filtros {
  margin-bottom: 1rem;
}

.buscador {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  overflow: hidden;
}

.tabla th,
.tabla td {
  text-align: left;
  padding: 0.75rem;
  border-bottom: 1px solid #f3f4f6;
  font-size: 0.875rem;
}

.tabla th {
  background-color: #f9fafb;
  color: #6b7280;
  font-weight: 500;
}

.secundario {
  display: block;
  color: #6b7280;
  font-size: 0.75rem;
}

.error {
  color: #b91c1c;
  font-size: 0.875rem;
}

.vacio {
  color: #6b7280;
  font-size: 0.875rem;
}
</style>