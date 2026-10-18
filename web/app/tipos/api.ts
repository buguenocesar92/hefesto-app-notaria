/**
 * Tipos que viajan entre la API y la interfaz.
 *
 * DEL SEMAFORO
 * -------------
 * `nivel` es UN NIVEL YA CALCULADO por la API. La interfaz no recalcula
 * cuando algo esta atrasado: pinta lo que le dicen.
 *
 * Es una decision deliberada. Si el navegador decidiera el color con su
 * propia regla, habria dos reglas en el sistema, y con el tiempo
 * divergirian: la tabla mostraria verde y el tablero mostraria rojo para la
 * misma etapa. Con una sola regla, que esta en el servidor y esta probada,
 * las dos pantallas cuentan lo mismo.
 */

export type NivelSemaforo = 'sin_umbral' | 'verde' | 'amarillo' | 'rojo' | 'terminado';

export type EstadoEtapa = 'pendiente' | 'en_curso' | 'bloqueada' | 'terminada' | 'omitida';

export interface Etapa {
  id: string;
  expediente_id: string;
  orden: number;
  nombre: string;
  descripcion: string | null;
  estado: EstadoEtapa;
  estado_etiqueta: string;
  requiere_antecedente: boolean;
  iniciada_en: string | null;
  terminada_en: string | null;
  observacion: string | null;
  profesional_responsable_id: string | null;

  // El semaforo, ya resuelto.
  nivel: NivelSemaforo;
  nivel_etiqueta: string;
  atrasada: boolean;
  dias_en_estado: number;
  dias_alerta: number | null;
  dias_critico: number | null;
  semaforo_calculado_en: string | null;

  // Texto para mostrarle a la persona. Cuando el nivel es `sin_umbral`, dice
  // por que no se marco nada.
  aviso: string | null;
  antecedentes_pendientes: number;
}

export interface Expediente {
  id: string;
  folio: string;
  numero_protocolo: string | null;
  descripcion: string | null;
  estado: string;
  estado_etiqueta: string;
  fecha_inicio: string | null;
  fecha_cierre: string | null;
  profesional_responsable_id: string | null;
  nivel: NivelSemaforo;
  nivel_etiqueta: string;
  atrasado: boolean;
  tipo_operacion?: {
    id: string;
    codigo: string;
    nombre: string;
    color: string | null;
  };
  etapas?: Etapa[];
  antecedentes_pendientes?: number;
  documentos_vigentes?: number;
}

export interface Antecedente {
  id: string;
  descripcion: string;
  solicitado_a: string | null;
  estado: 'pendiente' | 'recibido' | 'rechazado' | 'descartado';
  responsable_id: string | null;
  solicitado_en: string | null;
  recibido_en: string | null;
  respuesta: string | null;
}

export interface VersionDocumento {
  id: string;
  version: number;
  vigente: boolean;
  vigente_hasta: string | null;
  fecha_documento: string | null;
  nombre_archivo: string;
  mime: string | null;
  tamano_bytes: number | null;
  hash_sha256: string | null;
  observacion: string | null;
  descargar: string;
}

export interface Documento {
  id: string;
  expediente_id: string;
  nombre: string;
  tipo: string | null;
  estado: 'vigente' | 'reemplazado';
  estado_etiqueta: string;
  version_actual: number;
  fecha_documento: string | null;
  observacion: string | null;
  reemplazado_en: string | null;
  versiones: VersionDocumento[];
}

export interface FilaTablero {
  etapa_id: string;
  expediente_id: string;
  folio: string | null;
  numero_protocolo: string | null;
  tipo_operacion: string | null;
  etapa: string;
  estado: EstadoEtapa;
  nivel: NivelSemaforo;
  dias_en_estado: number;
  dias_alerta: number | null;
  dias_critico: number | null;
  antecedentes_pendientes: number;
}

export interface Tablero {
  sin_umbral: boolean;
  aviso: string | null;
  items: FilaTablero[];
  conteo: {
    rojo: number;
    amarillo: number;
    verde: number;
  };
  alertas_abiertas?: number;
  expedientes_abiertos?: number;
  antecedentes_pendientes?: number;
}

export interface Sesion {
  nombre: string;
  rol: string;
  rol_etiqueta: string;
  notaria: string;
}

export interface ApiError {
  mensaje?: string;
  errores?: Record<string, string[]>;
}