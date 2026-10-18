<template>
  <main class="pagina">
    <h1>Configuracion de la notaria</h1>

    <!--
      EL AVISO, EN LA PARTE SUPERIOR DE LA PANTALLA
      =============================================
      No es decoracion. Los dias que se escriben aca son una alarma interna de
      la notaria. Quien los escriba tiene que entender que esta poniendo el
      numero con el que su notaria se va a alarmar, y que ese numero no sale de
      ninguna norma.
    -->
    <section class="advertencia">
      <h2>Estos dias no son plazos legales</h2>
      <p>
        Son el criterio con el que <strong>su notaria</strong> decide conviene mirar una etapa
        que lleva esperando. No salen de ninguna norma, no los calcula esta aplicacion y no
        dicen si un tramite es valido.
      </p>
      <p>
        Si la notaria necesita trabajar con plazos legales, esos los maneja un notario, con su
        fundamento, y no se escriben aca.
      </p>
    </section>

    <p v-if="mensaje" class="mensaje">{{ mensaje }}</p>
    <p v-if="error" class="error">{{ error }}</p>

    <form class="formulario" @submit.prevent="guardar">
      <h2>Umbrales del semaforo</h2>

      <label for="alerta">
        Dias de espera antes de marcar en amarillo
      </label>
      <input
        id="alerta"
        v-model.number="alerta"
        type="number"
        min="1"
        max="3650"
        placeholder="Sin configurar"
      >
      <p class="ayuda">
        Si lo deja vacio, el semaforo no marca nada. Es lo recomendado antes de que la notaria
        decida su criterio.
      </p>

      <label for="critico">
        Dias de espera antes de marcar en rojo
      </label>
      <input
        id="critico"
        v-model.number="critico"
        type="number"
        min="1"
        max="3650"
        placeholder="Sin configurar"
      >
      <p class="ayuda">
        Si lo deja vacio, nunca aparece el rojo: la etapa queda en amarillo.
      </p>

      <button class="boton" type="submit" :disabled="enviando">
        {{ enviando ? 'Guardando...' : 'Guardar umbrales' }}
      </button>
    </form>

    <!--
      LOS PARAMETROS QUE UN NOTARIO DEBE CONFIRMAR

      Vienen VACIOS. Esta aplicacion no pone ningun valor, porque ponerlo seria
      inventar un plazo. Quien los carga deja constancia de con que fundamento
      los fijo, y eso queda guardado con su nombre y la fecha.
    -->
    <section class="formulario">
      <h2>Parametros que un notario debe confirmar</h2>

      <p class="advertencia-interna">
        Estos campos vienen vacios a proposito. Esta aplicacion no conoce plazos legales y no
        propone ninguno. El valor y el fundamento los carga un notario de la notaria.
      </p>

      <article v-for="(parametro, clave) in parametros" :key="clave" class="parametro">
        <h3>{{ parametro.etiqueta }}</h3>
        <p v-if="parametro.ayuda" class="ayuda">{{ parametro.ayuda }}</p>

        <p v-if="parametro.confirmado_en" class="confirmado">
          Confirmado el {{ fechaCorta(parametro.confirmado_en) }}
          <template v-if="parametro.valor !== null">
            · valor: <strong>{{ parametro.valor }}</strong>
          </template>
        </p>
        <p v-else class="pendiente">Pendiente de confirmar.</p>

        <details>
          <summary>Confirmar este dato</summary>

          <label :for="`valor-${clave}`">Valor</label>
          <input :id="`valor-${clave}`" v-model="valores[clave]" type="text">

          <label :for="`fundamento-${clave}`">Fundamento (con que criterio se fijo)</label>
          <textarea :id="`fundamento-${clave}`" v-model="fundamentos[clave]" rows="3" />

          <button
            class="boton boton--chico"
            type="button"
            @click="confirmar(String(clave))"
          >
            Guardar dato
          </button>
        </details>
      </article>
    </section>
  </main>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

useHead({ title: 'Configuracion · Gestion de Notarias' });

interface Parametro {
  etiqueta: string;
  valor: string | number | null;
  fundamento: string | null;
  confirmado_por: string | null;
  confirmado_en: string | null;
  ayuda?: string;
}

const mensaje = ref<string | null>(null);
const error = ref<string | null>(null);
const enviando = ref(false);

const alerta = ref<number | null>(null);
const critico = ref<number | null>(null);

const valores = reactive<Record<string, string>>({});
const fundamentos = reactive<Record<string, string>>({});

const { data, refresh } = await useFetch<{
  datos: {
    dias_alerta_por_defecto: number | null;
    dias_critico_por_defecto: number | null;
  };
  advertencia: string;
  parametros: Record<string, Parametro>;
}>('/api/v1/configuracion');

const parametros = computed<Record<string, Parametro>>(() => data.value?.parametros ?? {});

onMounted(() => {
  alerta.value = data.value?.datos.dias_alerta_por_defecto ?? null;
  critico.value = data.value?.datos.dias_critico_por_defecto ?? null;
});

async function guardar(): Promise<void> {
  enviando.value = true;
  mensaje.value = null;
  error.value = null;

  try {
    await $fetch('/api/v1/configuracion', {
      method: 'PATCH',
      body: {
        dias_alerta_por_defecto: alerta.value,
        dias_critico_por_defecto: critico.value,
      },
    });

    mensaje.value = 'Umbrales guardados. El cambio no reescribe los expedientes ya abiertos.';

    await refresh();
  } catch (fallo) {
    error.value = mensajeDeError(fallo);
  } finally {
    enviando.value = false;
  }
}

async function confirmar(clave: string): Promise<void> {
  error.value = null;

  try {
    await $fetch(`/api/v1/configuracion/parametros/${clave}`, {
      method: 'POST',
      body: {
        valor: valores[clave],
        fundamento: fundamentos[clave],
      },
    });

    mensaje.value = 'Dato confirmado y registrado.';

    await refresh();
  } catch (fallo) {
    error.value = mensajeDeError(fallo);
  }
}
</script>

<style scoped>
.pagina {
  max-width: 48rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 4rem;
}

h1 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 1.25rem;
}

h2 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

h3 {
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.advertencia,
.advertencia-interna {
  border-left: 3px solid #ca8a04;
  background-color: #fffbeb;
  padding: 1rem;
  border-radius: 0.25rem;
  margin-bottom: 1.25rem;
  font-size: 0.875rem;
  color: #4b5563;
}

.advertencia h2 {
  color: #92400e;
}

.advertencia p:last-child,
.advertencia-interna:last-child {
  margin-bottom: 0;
}

.formulario {
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.25rem;
  margin-bottom: 1.25rem;
}

label {
  display: block;
  font-size: 0.875rem;
  color: #374151;
  margin: 1rem 0 0.25rem;
}

input,
textarea {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.ayuda {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.parametro {
  border-top: 1px solid #f3f4f6;
  padding: 1rem 0;
}

.pendiente {
  font-size: 0.8125rem;
  color: #b45309;
}

.confirmado {
  font-size: 0.8125rem;
  color: #166534;
}

details {
  margin-top: 0.75rem;
}

summary {
  font-size: 0.8125rem;
  cursor: pointer;
  color: #1d4ed8;
}

.boton {
  margin-top: 1rem;
  padding: 0.625rem 1rem;
  background-color: #111827;
  color: #ffffff;
  border: none;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  cursor: pointer;
}

.boton--chico {
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
}

.boton:disabled {
  background-color: #9ca3af;
  cursor: not-allowed;
}

.mensaje {
  color: #166534;
  font-size: 0.875rem;
}

.error {
  color: #b91c1c;
  font-size: 0.875rem;
}
</style>