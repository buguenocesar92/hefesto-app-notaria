<template>
  <main class="tablero">
    <header class="encabezado">
      <h1>Tramites atrasados</h1>
      <p class="subtitulo">
        {{ new Date().toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' }) }}
      </p>
    </header>

    <!--
      EL AVISO QUE NO SE PUEDE OCULTAR
      ================================
      Si la notaria no configuro el umbral, el tablero esta vacio y aparece
      esto. Es tentador esconderlo y mostrar el tablero en blanco, pero una
      pantalla vacia sin explicacion hace pensar que el sistema fallo. El
      aviso explica que no hay umbral, y que eso es una falta de
      configuracion, no un vencimiento.
    -->
    <section v-if="datos?.sin_umbral" class="aviso aviso--neutro">
      <h2>El semaforo esta apagado</h2>
      <p>{{ datos.aviso }}</p>
      <NuxtLink class="boton" to="/configuracion">Configurar el umbral</NuxtLink>
    </section>

    <template v-else>
      <section class="cifras">
        <article class="cifra cifra--rojo">
          <span class="cifra__numero">{{ datos?.conteo.rojo ?? 0 }}</span>
          <span class="cifra__etiqueta">Muy atrasadas</span>
        </article>
        <article class="cifra cifra--amarillo">
          <span class="cifra__numero">{{ datos?.conteo.amarillo ?? 0 }}</span>
          <span class="cifra__etiqueta">Atrasadas</span>
        </article>
        <article class="cifra">
          <span class="cifra__numero">{{ datos?.expedientes_abiertos ?? 0 }}</span>
          <span class="cifra__etiqueta">Expedientes abiertos</span>
        </article>
        <article class="cifra">
          <span class="cifra__numero">{{ datos?.antecedentes_pendientes ?? 0 }}</span>
          <span class="cifra__etiqueta">Antecedentes sin llegar</span>
        </article>
      </section>

      <!--
        NOTA AL PIE, SIEMPRE VISIBLE
        ===========================
        Los colores son una alarma interna de la notaria, con los dias que la
        notaria configuro. No son plazos legales, y este sistema no conoce
        ninguno. Conviene tenerlo a mano en la pantalla, no escondido en una
        ayuda, porque alguien va a leer "rojo" como "vencido" tarde o temprano.
      -->
      <p class="nota-legal">
        Los colores comparan los dias que cada etapa lleva en su estado actual contra
        los umbrales que esta notaria configuro. <strong>No son plazos legales.</strong>
        Un rojo no significa que algo se haya vencido: significa que conviene mirarlo.
      </p>

      <section v-if="(datos?.items.length ?? 0) === 0" class="aviso aviso--bien">
        <h2>Nada atrasado</h2>
        <p>No hay etapas esperando mas dias de los que la notaria configuro.</p>
      </section>

      <table v-else class="tabla">
        <thead>
          <tr>
            <th>Expediente</th>
            <th>Etapa</th>
            <th>Estado</th>
            <th class="numero">Dias esperando</th>
            <th class="numero">Umbral</th>
            <th class="numero">Antecedentes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fila in datos?.items" :key="fila.etapa_id">
            <td>
              <NuxtLink :to="`/expedientes/${fila.expediente_id}`">
                {{ fila.folio }}
              </NuxtLink>
              <span v-if="fila.numero_protocolo" class="secundario">
                {{ fila.numero_protocolo }}
              </span>
              <span v-if="fila.tipo_operacion" class="secundario">{{ fila.tipo_operacion }}</span>
            </td>
            <td>{{ fila.etapa }}</td>
            <td>
              <IndicadorSemaforo
                :nivel="fila.nivel"
                :etiqueta="fila.nivel === 'rojo' ? 'Muy atrasada' : 'Atrasada'"
                :dias-en-estado="fila.dias_en_estado"
              />
            </td>
            <td class="numero">{{ fila.dias_en_estado }}</td>
            <td class="numero">{{ fila.dias_alerta ?? '-' }}</td>
            <td class="numero">
              <span v-if="fila.antecedentes_pendientes > 0" class="insistente">
                {{ fila.antecedentes_pendientes }}
              </span>
              <span v-else>-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </main>
</template>

<script setup lang="ts">
import type { Tablero } from '~/tipos/api';

definePageMeta({
  middleware: 'auth',
});

useHead({ title: 'Tramites atrasados · Gestion de Notarias' });

/**
 * El tablero.
 *
 * Los datos vienen ya calculados desde la API, incluido el nivel de cada
 * etapa. Esta pagina no cuenta dias ni decide colores: si lo hiciera, la
 * interfaz tendria una segunda regla del semaforo, y las dos dirian cosas
 * distintas con el tiempo.
 */
const { data: datos } = await useFetch<Tablero>('/api/v1/tablero');
</script>

<style scoped>
.tablero {
  max-width: 72rem;
  margin: 0 auto;
  padding: 1.5rem 1rem 4rem;
}

.encabezado {
  margin-bottom: 1.5rem;
}

h1 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
}

.subtitulo {
  color: #6b7280;
  font-size: 0.875rem;
  text-transform: capitalize;
}

.cifras {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.cifra {
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.cifra--rojo {
  border-color: #fecaca;
  background-color: #fef2f2;
}

.cifra--amarillo {
  border-color: #fde68a;
  background-color: #fffbeb;
}

.cifra__numero {
  font-size: 1.75rem;
  font-weight: 600;
  color: #111827;
}

.cifra__etiqueta {
  font-size: 0.8125rem;
  color: #6b7280;
}

.nota-legal {
  font-size: 0.8125rem;
  color: #4b5563;
  background-color: #f9fafb;
  border-left: 3px solid #9ca3af;
  padding: 0.75rem 1rem;
  border-radius: 0.25rem;
  margin-bottom: 1.25rem;
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
  vertical-align: top;
}

.tabla th {
  background-color: #f9fafb;
  color: #6b7280;
  font-weight: 500;
}

.numero {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.secundario {
  display: block;
  color: #6b7280;
  font-size: 0.75rem;
}

.insistente {
  color: #b91c1c;
  font-weight: 600;
}

.aviso {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.5rem;
  background-color: #ffffff;
}

.aviso h2 {
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.aviso p {
  color: #4b5563;
  margin-bottom: 1rem;
}

.boton {
  display: inline-block;
  background-color: #111827;
  color: #ffffff;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  text-decoration: none;
}
</style>