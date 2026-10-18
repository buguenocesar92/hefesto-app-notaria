<template>
  <div class="semaforo">
    <!--
      El punto de color. El color lo decide la API, no esta interfaz: la
      regla del semaforo vive en el servidor y esta probada ahi.
    -->
    <span
      class="punto"
      :class="`punto--${nivel}`"
      :title="etiqueta"
      :aria-label="etiqueta"
      role="img"
    />

    <span class="texto">{{ texto }}</span>
  </div>
</template>

<script setup lang="ts">
import type { NivelSemaforo } from '~/tipos/api';

/**
 * Muestra el nivel del semaforo de una etapa.
 *
 * LO QUE ESTE COMPONENTE NO HACE
 * ------------------------------
 * No decide el color. Recibe `nivel` ya calculado y lo pinta. Si esta
 * interfaz tuviera su propia regla de dias, habria dos reglas que con el
 * tiempo dirian cosas distintas sobre la misma etapa.
 *
 * Cuando el nivel es `sin_umbral`, el componente lo dice, en vez de
 * esconderse: si una etapa aparece sin color y sin explicacion, quien la mira
 * va a pensar que el sistema esta roto.
 */
const props = defineProps<{
  nivel: NivelSemaforo;
  etiqueta: string;
  diasEnEstado?: number;
}>();

const texto = computed<string>(() => {
  if (props.nivel === 'sin_umbral') {
    return 'Sin umbral configurado';
  }

  if (props.nivel === 'terminado') {
    return 'Terminada';
  }

  return props.etiqueta;
});
</script>

<style scoped>
.semaforo {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.punto {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 9999px;
  display: inline-block;
  flex-shrink: 0;
}

.punto--verde {
  background-color: #16a34a;
}

.punto--amarillo {
  background-color: #ca8a04;
}

.punto--rojo {
  background-color: #dc2626;
}

.punto--sin_umbral {
  background-color: #9ca3af;
}

.punto--terminado {
  background-color: #d1d5db;
}

.texto {
  font-size: 0.8125rem;
  color: #374151;
}
</style>