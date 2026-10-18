<template>
  <main class="expediente">
    <NuxtLink to="/" class="volver">&larr; Volver al tablero</NuxtLink>

    <template v-if="datos">
      <header class="encabezado">
        <h1>
          {{ datos.datos.folio }}
          <span v-if="datos.datos.numero_protocolo" class="protocolo">
            {{ datos.datos.numero_protocolo }}
          </span>
        </h1>
        <p class="subtitulo">
          {{ datos.datos.tipo_operacion?.nombre }} ·
          abierto el {{ fechaCorta(datos.datos.fecha_inicio) }} ·
          {{ datos.datos.estado_etiqueta }}
        </p>
        <p v-if="datos.datos.descripcion" class="descripcion">
          {{ datos.datos.descripcion }}
        </p>
      </header>

      <!--
        LAS ETAPAS CON SU SEMAFORO

        Cada etapa muestra el nivel que calculo la API, los dias que lleva en
        su estado, y el umbral con el que se compara. Cuando no hay umbral, la
        etapa lo dice, en vez de quedar gris sin explicacion.
      -->
      <section class="bloque">
        <h2>Etapas</h2>

        <ol class="etapas">
          <li v-for="etapa in datos.datos.etapas" :key="etapa.id" class="etapa">
            <div class="etapa__encabezado">
              <span class="etapa__orden">{{ etapa.orden }}</span>
              <span class="etapa__nombre">{{ etapa.nombre }}</span>
              <IndicadorSemaforo
                :nivel="etapa.nivel"
                :etiqueta="etapa.nivel_etiqueta"
                :dias-en-estado="etapa.dias_en_estado"
              />
            </div>

            <div class="etapa__detalle">
              <span>{{ etapa.estado_etiqueta }}</span>

              <span v-if="etapa.dias_alerta" class="umbral">
                lleva {{ etapa.dias_en_estado }} dias · umbral interno: {{ etapa.dias_alerta }} dias
              </span>

              <span v-if="etapa.requiere_antecedente && etapa.antecedentes_pendientes > 0" class="faltante">
                faltan {{ etapa.antecedentes_pendientes }} antecedente(s)
              </span>
            </div>

            <p v-if="etapa.aviso" class="aviso-etapa">{{ etapa.aviso }}</p>
          </li>
        </ol>
      </section>

      <!--
        DOCUMENTOS

        Aqui NO hay boton de borrar, y no es una omision de la pantalla: la API
        no tiene ruta DELETE de documentos. Un documento de un expediente no
        se borra, se reemplaza, y la version anterior queda.

        El historial completo se muestra, con la version vigente marcada.
      -->
      <section class="bloque">
        <h2>Documentos</h2>
        <p class="nota-bloque">
          Un documento de un expediente no se borra: se reemplaza. La version anterior queda
          y se puede descargar.
        </p>

        <p v-if="documentos.length === 0" class="vacio">
          Este expediente todavia no tiene documentos.
        </p>

        <article v-for="documento in documentos" :key="documento.id" class="documento">
          <header class="documento__encabezado">
            <span class="documento__nombre">{{ documento.nombre }}</span>
            <span class="etiqueta" :class="documento.estado === 'vigente' ? 'etiqueta--vigente' : 'etiqueta--reemplazado'">
              {{ documento.estado_etiqueta }}
            </span>
          </header>

          <ul class="versiones">
            <li v-for="version in documento.versiones" :key="version.id" class="version">
              <span class="version__numero">v{{ version.version }}</span>
              <span class="version__nombre">{{ version.nombre_archivo }}</span>
              <span class="version__fecha">{{ fechaCorta(version.fecha_documento) }}</span>
              <span v-if="version.vigente" class="version__vigente">vigente</span>
              <span v-else class="version__historica">
                vigente hasta el {{ fechaCorta(version.vigente_hasta) }}
              </span>
              <a :href="version.descargar" class="version__descargar">Descargar</a>
            </li>
          </ul>
        </article>
      </section>
    </template>
  </main>
</template>

<script setup lang="ts">
import type { Documento } from '~/tipos/api';

definePageMeta({
  middleware: 'auth',
});

const ruta = useRoute();

useHead({ title: 'Expediente · Gestion de Notarias' });

const { data: datos } = await useFetch<{ datos: Record<string, unknown> & { etapas: unknown[] }; nivel: string }>(
  `/api/v1/expedientes/${String(ruta.params.id)}`,
);

const { data: documentosDatos } = await useFetch<{ datos: Documento[] }>(
  `/api/v1/expedientes/${String(ruta.params.id)}/documentos`,
);

const documentos = computed<Documento[]>(() => documentosDatos.value?.datos ?? []);
</script>

<style scoped>
.expediente {
  max-width: 56rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 4rem;
}

.volver {
  display: inline-block;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

h1 {
  font-size: 1.5rem;
  font-weight: 600;
}

h2 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
}

.protocolo {
  font-size: 1rem;
  color: #6b7280;
  font-weight: 400;
}

.subtitulo,
.descripcion {
  color: #6b7280;
  font-size: 0.875rem;
}

.bloque {
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.25rem;
  margin-top: 1.25rem;
}

.etapas {
  list-style: none;
  margin: 0;
  padding: 0;
}

.etapa {
  padding: 0.75rem 0;
  border-bottom: 1px solid #f3f4f6;
}

.etapa:last-child {
  border-bottom: none;
}

.etapa__encabezado {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.etapa__orden {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 9999px;
  background-color: #f3f4f6;
  color: #6b7280;
  font-size: 0.75rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.etapa__nombre {
  font-weight: 500;
  font-size: 0.875rem;
  flex-grow: 1;
}

.etapa__detalle {
  display: flex;
  gap: 1rem;
  font-size: 0.8125rem;
  color: #6b7280;
  margin-top: 0.25rem;
  margin-left: 2.25rem;
}

.faltante {
  color: #b45309;
}

.aviso-etapa {
  font-size: 0.75rem;
  color: #6b7280;
  margin: 0.5rem 0 0 2.25rem;
  background-color: #f9fafb;
  padding: 0.5rem 0.75rem;
  border-radius: 0.25rem;
}

.nota-bloque {
  font-size: 0.8125rem;
  color: #6b7280;
  margin-bottom: 1rem;
}

.documento {
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.75rem;
}

.documento__encabezado {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.documento__nombre {
  font-weight: 500;
  font-size: 0.875rem;
}

.etiqueta {
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  border-radius: 0.25rem;
}

.etiqueta--vigente {
  background-color: #dcfce7;
  color: #166534;
}

.etiqueta--reemplazado {
  background-color: #f3f4f6;
  color: #6b7280;
}

.versiones {
  list-style: none;
  margin: 0;
  padding: 0;
}

.version {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.8125rem;
  padding: 0.25rem 0;
}

.version__numero {
  color: #6b7280;
  font-variant-numeric: tabular-nums;
}

.version__nombre {
  font-family: ui-monospace, monospace;
}

.version__fecha,
.version__historica {
  color: #6b7280;
}

.version__vigente {
  color: #166534;
  font-weight: 500;
}

.version__descargar {
  margin-left: auto;
}

.vacio {
  color: #6b7280;
  font-size: 0.875rem;
}
</style>