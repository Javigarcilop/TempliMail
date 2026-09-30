export interface ApiResponse {
  success: boolean;
  error?: string;
}

export interface DataResponse<T> extends ApiResponse {
  data: T;
}

export interface Usuario {
  id: number;
  nombre_usuario: string;
  correo: string;
}

export interface Contacto {
  id: number;
  nombre: string | null;
  apellidos: string | null;
  correo: string;
  telefono: string | null;
  empresa: string | null;
  cargo: string | null;
  /** ISO 8601 (UTC) si el contacto se dio de baja */
  baja_en: string | null;
  creado_en: string;
  ids_grupo: number[];
}

export interface Grupo {
  id: number;
  nombre: string;
  total_miembros: number;
}

export interface ResultadoImportacion {
  creados: number;
  duplicados: number;
  invalidos: { fila: number; correo: string; motivo: string }[];
}

export interface ContactoInput {
  nombre: string;
  apellidos: string;
  correo: string;
  telefono: string;
  empresa: string;
  cargo: string;
}

export interface Plantilla {
  id: number;
  nombre: string;
  asunto: string;
  contenido_html: string;
  creado_en: string;
  actualizado_en: string;
}

export interface PlantillaInput {
  id?: number | null;
  nombre: string;
  asunto: string;
  contenido_html: string;
}

export type EstadoCampana = 'scheduled' | 'processing' | 'completed' | 'cancelled';

export interface Campana {
  id: number;
  nombre: string | null;
  tipo: 'mass' | 'single';
  asunto: string;
  estado: EstadoCampana;
  /** ISO 8601 (UTC). null = en cola para enviarse ya */
  programado_en: string | null;
  creado_en: string;
  actualizado_en: string;
  nombre_plantilla: string | null;
  total_destinatarios: number;
  enviados: number;
  fallidos: number;
  pendientes: number;
  omitidos: number;
}

export type EstadoEntrega = 'pending' | 'sent' | 'failed' | 'skipped';

export interface Entrega {
  id: number;
  estado: EstadoEntrega;
  reintentos: number;
  enviado_en: string | null;
  mensaje_error: string | null;
  nombre: string | null;
  apellidos: string | null;
  correo: string;
}

export interface EnvioMasivoInput {
  plantilla_id: number;
  ids_contacto: number[];
  nombre?: string;
  /** ISO 8601 con zona horaria (p. ej. new Date(...).toISOString()) */
  programado_en?: string;
}

export interface EnvioMasivoRespuesta extends ApiResponse {
  campana_id: number;
  programado: boolean;
  destinatarios: number;
  excluidos: number;
}

export interface VistaPreviaCorreoInput {
  asunto: string;
  contenido_html: string;
  contacto_id?: number;
}

export interface VistaPreviaCorreo {
  asunto: string;
  cuerpo: string;
}

export interface EstadisticasPanel {
  total_campanas: number;
  total_contactos: number;
  total_plantillas: number;
  total_enviados: number;
  total_fallidos: number;
}

export interface DiaActividad {
  /** YYYY-MM-DD (UTC) */
  fecha: string;
  enviados: number;
}

export interface ResumenPanel {
  plantilla_top: { nombre: string; total: number } | null;
  contacto_top: { nombre: string; total: number } | null;
}

/** Variables que se pueden usar en plantillas: {{nombre}}, {{nombre|amigo}}... */
export const VARIABLES_PLANTILLA: { key: string; label: string }[] = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'apellidos', label: 'Apellidos' },
  { key: 'nombre_completo', label: 'Nombre completo' },
  { key: 'correo', label: 'Correo' },
  { key: 'empresa', label: 'Empresa' },
  { key: 'cargo', label: 'Cargo' },
  { key: 'enlace_baja', label: 'Enlace de baja' }
];
