import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  ApiResponse,
  Campana,
  Contacto,
  ContactoInput,
  DataResponse,
  DiaActividad,
  Entrega,
  EnvioMasivoInput,
  EnvioMasivoRespuesta,
  EstadisticasPanel,
  Grupo,
  ResultadoImportacion,
  ResumenPanel,
  Plantilla,
  PlantillaInput,
  Usuario,
  VistaPreviaCorreo,
  VistaPreviaCorreoInput
} from '../models/api.models';

/**
 * Único punto de acceso a la API. El JWT lo añade `interceptorAutenticacion`,
 * que también gestiona el cierre de sesión ante un 401.
 */
@Injectable({
  providedIn: 'root'
})
export class ApiService {

  private readonly baseUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  // =====================================
  // AUTENTICACION
  // =====================================

  iniciarSesion(data: { nombre_usuario: string; contrasena: string }): Observable<ApiResponse & { token: string }> {
    return this.http.post<ApiResponse & { token: string }>(`${this.baseUrl}/login`, data);
  }

  registrar(data: { nombre_usuario: string; correo: string; contrasena: string }): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/register`, data);
  }

  obtenerPerfil(): Observable<DataResponse<Usuario>> {
    return this.http.get<DataResponse<Usuario>>(`${this.baseUrl}/me`);
  }

  actualizarPerfil(correo: string): Observable<DataResponse<Usuario>> {
    return this.http.put<DataResponse<Usuario>>(`${this.baseUrl}/me`, { correo });
  }

  /** Devuelve un token nuevo: el cambio invalida las demás sesiones. */
  cambiarContrasena(contrasenaActual: string, contrasenaNueva: string): Observable<ApiResponse & { token: string }> {
    return this.http.put<ApiResponse & { token: string }>(`${this.baseUrl}/me/contrasena`, {
      contrasena_actual: contrasenaActual,
      contrasena_nueva: contrasenaNueva
    });
  }

  /** Invalida en el servidor todos los tokens del usuario. */
  cerrarSesion(): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/logout`, {});
  }

  // =====================================
  // CONTACTOS
  // =====================================

  obtenerContactos(): Observable<DataResponse<Contacto[]>> {
    return this.http.get<DataResponse<Contacto[]>>(`${this.baseUrl}/contactos`);
  }

  crearContacto(data: ContactoInput): Observable<ApiResponse & { id: number }> {
    return this.http.post<ApiResponse & { id: number }>(`${this.baseUrl}/contactos`, data);
  }

  actualizarContacto(id: number, data: ContactoInput): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contactos/${id}`, data);
  }

  eliminarContacto(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/contactos/${id}`);
  }

  establecerSuscripcionContacto(id: number, subscribed: boolean): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contactos/${id}/subscription`, { subscribed });
  }

  importarContactos(contactos: Partial<ContactoInput>[], grupoId?: number): Observable<ApiResponse & ResultadoImportacion> {
    return this.http.post<ApiResponse & ResultadoImportacion>(`${this.baseUrl}/contactos/import`, {
      contactos,
      ...(grupoId ? { grupo_id: grupoId } : {})
    });
  }

  establecerGruposContacto(id: number, idsGrupo: number[]): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contactos/${id}/grupos`, { ids_grupo: idsGrupo });
  }

  // =====================================
  // GRUPOS
  // =====================================

  obtenerGrupos(): Observable<DataResponse<Grupo[]>> {
    return this.http.get<DataResponse<Grupo[]>>(`${this.baseUrl}/grupos`);
  }

  crearGrupo(nombre: string): Observable<ApiResponse & { id: number }> {
    return this.http.post<ApiResponse & { id: number }>(`${this.baseUrl}/grupos`, { nombre });
  }

  renombrarGrupo(id: number, nombre: string): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/grupos/${id}`, { nombre });
  }

  eliminarGrupo(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/grupos/${id}`);
  }

  // =====================================
  // PLANTILLAS
  // =====================================

  obtenerPlantillas(): Observable<DataResponse<Plantilla[]>> {
    return this.http.get<DataResponse<Plantilla[]>>(`${this.baseUrl}/plantillas`);
  }

  crearPlantilla(data: PlantillaInput): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/plantillas`, data);
  }

  actualizarPlantilla(id: number, data: PlantillaInput): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/plantillas/${id}`, data);
  }

  eliminarPlantilla(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/plantillas/${id}`);
  }

  subirArchivoPlantilla(formData: FormData): Observable<ApiResponse & { html: string }> {
    return this.http.post<ApiResponse & { html: string }>(`${this.baseUrl}/upload-template-file`, formData);
  }

  // =====================================
  // CORREO
  // =====================================

  enviarCorreoIndividual(data: { destinatario: string; asunto: string; cuerpo: string }): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/send-mail`, data);
  }

  /** Encola una campaña (inmediata o programada); la envía el worker del servidor. */
  enviarCorreoMasivo(data: EnvioMasivoInput): Observable<EnvioMasivoRespuesta> {
    return this.http.post<EnvioMasivoRespuesta>(`${this.baseUrl}/send-massive`, {
      plantilla_id: data.plantilla_id,
      ids_contacto: data.ids_contacto,
      ...(data.nombre ? { nombre: data.nombre } : {}),
      ...(data.programado_en ? { programado_en: data.programado_en } : {})
    });
  }

  /** Asunto y cuerpo con las variables ya sustituidas (contacto real o datos de ejemplo). */
  previsualizarCorreo(data: VistaPreviaCorreoInput): Observable<DataResponse<VistaPreviaCorreo>> {
    return this.http.post<DataResponse<VistaPreviaCorreo>>(`${this.baseUrl}/mail/preview`, data);
  }

  /** Envía una copia de prueba al correo del propio usuario. */
  enviarCorreoPrueba(data: VistaPreviaCorreoInput): Observable<ApiResponse & { enviado_a: string }> {
    return this.http.post<ApiResponse & { enviado_a: string }>(`${this.baseUrl}/mail/test`, data);
  }

  sugerirAsuntos(topic: string): Observable<ApiResponse & { subjects: string[] }> {
    return this.http.post<ApiResponse & { subjects: string[] }>(`${this.baseUrl}/ai/suggest-asunto`, { topic });
  }

  // =====================================
  // HISTORIAL / CAMPAÑAS
  // =====================================

  obtenerHistorial(): Observable<DataResponse<Campana[]>> {
    return this.http.get<DataResponse<Campana[]>>(`${this.baseUrl}/history`);
  }

  obtenerEntregasCampana(campanaId: number): Observable<DataResponse<Entrega[]>> {
    return this.http.get<DataResponse<Entrega[]>>(`${this.baseUrl}/history/${campanaId}/deliveries`);
  }

  cancelarCampana(campanaId: number): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/history/${campanaId}/cancel`, {});
  }

  reintentarEntregasFallidas(campanaId: number): Observable<ApiResponse & { reencolados: number }> {
    return this.http.post<ApiResponse & { reencolados: number }>(`${this.baseUrl}/history/${campanaId}/retry-failed`, {});
  }

  // =====================================
  // PANEL
  // =====================================

  obtenerEstadisticas(): Observable<DataResponse<EstadisticasPanel>> {
    return this.http.get<DataResponse<EstadisticasPanel>>(`${this.baseUrl}/dashboard/stats`);
  }

  obtenerActividad(): Observable<DataResponse<DiaActividad[]>> {
    return this.http.get<DataResponse<DiaActividad[]>>(`${this.baseUrl}/dashboard/activity`);
  }

  obtenerResumen(): Observable<DataResponse<ResumenPanel>> {
    return this.http.get<DataResponse<ResumenPanel>>(`${this.baseUrl}/dashboard/summary`);
  }
}
