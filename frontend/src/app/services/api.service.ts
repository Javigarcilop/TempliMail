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
 * Único punto de acceso a la API. El JWT lo añade `authInterceptor`,
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

  login(data: { nombre_usuario: string; contrasena: string }): Observable<ApiResponse & { token: string }> {
    return this.http.post<ApiResponse & { token: string }>(`${this.baseUrl}/login`, data);
  }

  register(data: { nombre_usuario: string; correo: string; contrasena: string }): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/register`, data);
  }

  me(): Observable<DataResponse<Usuario>> {
    return this.http.get<DataResponse<Usuario>>(`${this.baseUrl}/me`);
  }

  updateProfile(correo: string): Observable<DataResponse<Usuario>> {
    return this.http.put<DataResponse<Usuario>>(`${this.baseUrl}/me`, { correo });
  }

  /** Devuelve un token nuevo: el cambio invalida las demás sesiones. */
  changePassword(contrasenaActual: string, contrasenaNueva: string): Observable<ApiResponse & { token: string }> {
    return this.http.put<ApiResponse & { token: string }>(`${this.baseUrl}/me/contrasena`, {
      contrasena_actual: contrasenaActual,
      contrasena_nueva: contrasenaNueva
    });
  }

  /** Invalida en el servidor todos los tokens del usuario. */
  logout(): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/logout`, {});
  }

  // =====================================
  // CONTACTOS
  // =====================================

  getContacts(): Observable<DataResponse<Contacto[]>> {
    return this.http.get<DataResponse<Contacto[]>>(`${this.baseUrl}/contactos`);
  }

  addContact(data: ContactoInput): Observable<ApiResponse & { id: number }> {
    return this.http.post<ApiResponse & { id: number }>(`${this.baseUrl}/contactos`, data);
  }

  updateContact(id: number, data: ContactoInput): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contactos/${id}`, data);
  }

  deleteContact(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/contactos/${id}`);
  }

  setContactSubscription(id: number, subscribed: boolean): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contactos/${id}/subscription`, { subscribed });
  }

  importContacts(contactos: Partial<ContactoInput>[], grupoId?: number): Observable<ApiResponse & ResultadoImportacion> {
    return this.http.post<ApiResponse & ResultadoImportacion>(`${this.baseUrl}/contactos/import`, {
      contactos,
      ...(grupoId ? { grupo_id: grupoId } : {})
    });
  }

  setContactGroups(id: number, idsGrupo: number[]): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contactos/${id}/grupos`, { ids_grupo: idsGrupo });
  }

  // =====================================
  // GRUPOS
  // =====================================

  getGroups(): Observable<DataResponse<Grupo[]>> {
    return this.http.get<DataResponse<Grupo[]>>(`${this.baseUrl}/grupos`);
  }

  createGroup(nombre: string): Observable<ApiResponse & { id: number }> {
    return this.http.post<ApiResponse & { id: number }>(`${this.baseUrl}/grupos`, { nombre });
  }

  renameGroup(id: number, nombre: string): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/grupos/${id}`, { nombre });
  }

  deleteGroup(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/grupos/${id}`);
  }

  // =====================================
  // PLANTILLAS
  // =====================================

  getTemplates(): Observable<DataResponse<Plantilla[]>> {
    return this.http.get<DataResponse<Plantilla[]>>(`${this.baseUrl}/plantillas`);
  }

  addTemplate(data: PlantillaInput): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/plantillas`, data);
  }

  updateTemplate(id: number, data: PlantillaInput): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/plantillas/${id}`, data);
  }

  deleteTemplate(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/plantillas/${id}`);
  }

  uploadTemplateFile(formData: FormData): Observable<ApiResponse & { html: string }> {
    return this.http.post<ApiResponse & { html: string }>(`${this.baseUrl}/upload-template-file`, formData);
  }

  // =====================================
  // CORREO
  // =====================================

  sendSingleMail(data: { destinatario: string; asunto: string; cuerpo: string }): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/send-mail`, data);
  }

  /** Encola una campaña (inmediata o programada); la envía el worker del servidor. */
  sendMassiveMail(data: EnvioMasivoInput): Observable<EnvioMasivoRespuesta> {
    return this.http.post<EnvioMasivoRespuesta>(`${this.baseUrl}/send-massive`, {
      plantilla_id: data.plantilla_id,
      ids_contacto: data.ids_contacto,
      ...(data.nombre ? { nombre: data.nombre } : {}),
      ...(data.programado_en ? { programado_en: data.programado_en } : {})
    });
  }

  /** Asunto y cuerpo con las variables ya sustituidas (contacto real o datos de ejemplo). */
  previewMail(data: VistaPreviaCorreoInput): Observable<DataResponse<VistaPreviaCorreo>> {
    return this.http.post<DataResponse<VistaPreviaCorreo>>(`${this.baseUrl}/mail/preview`, data);
  }

  /** Envía una copia de prueba al correo del propio usuario. */
  sendTestMail(data: VistaPreviaCorreoInput): Observable<ApiResponse & { enviado_a: string }> {
    return this.http.post<ApiResponse & { enviado_a: string }>(`${this.baseUrl}/mail/test`, data);
  }

  suggestSubjects(topic: string): Observable<ApiResponse & { subjects: string[] }> {
    return this.http.post<ApiResponse & { subjects: string[] }>(`${this.baseUrl}/ai/suggest-asunto`, { topic });
  }

  // =====================================
  // HISTORIAL / CAMPAÑAS
  // =====================================

  getHistory(): Observable<DataResponse<Campana[]>> {
    return this.http.get<DataResponse<Campana[]>>(`${this.baseUrl}/history`);
  }

  getCampaignDeliveries(campanaId: number): Observable<DataResponse<Entrega[]>> {
    return this.http.get<DataResponse<Entrega[]>>(`${this.baseUrl}/history/${campanaId}/deliveries`);
  }

  cancelCampaign(campanaId: number): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/history/${campanaId}/cancel`, {});
  }

  retryFailedDeliveries(campanaId: number): Observable<ApiResponse & { reencolados: number }> {
    return this.http.post<ApiResponse & { reencolados: number }>(`${this.baseUrl}/history/${campanaId}/retry-failed`, {});
  }

  // =====================================
  // PANEL
  // =====================================

  getDashboardStats(): Observable<DataResponse<EstadisticasPanel>> {
    return this.http.get<DataResponse<EstadisticasPanel>>(`${this.baseUrl}/dashboard/stats`);
  }

  getDashboardActivity(): Observable<DataResponse<DiaActividad[]>> {
    return this.http.get<DataResponse<DiaActividad[]>>(`${this.baseUrl}/dashboard/activity`);
  }

  getDashboardSummary(): Observable<DataResponse<ResumenPanel>> {
    return this.http.get<DataResponse<ResumenPanel>>(`${this.baseUrl}/dashboard/summary`);
  }
}
