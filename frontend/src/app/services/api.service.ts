import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  ApiResponse,
  Campaign,
  Contact,
  ContactInput,
  DashboardStats,
  DashboardSummary,
  DataResponse,
  Delivery,
  MailPreview,
  MailPreviewInput,
  MassiveMailInput,
  MassiveMailResponse,
  Template,
  TemplateInput,
  User
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
  // AUTH
  // =====================================

  login(data: { username: string; password: string }): Observable<ApiResponse & { token: string }> {
    return this.http.post<ApiResponse & { token: string }>(`${this.baseUrl}/login`, data);
  }

  register(data: { username: string; email: string; password: string }): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/register`, data);
  }

  me(): Observable<DataResponse<User>> {
    return this.http.get<DataResponse<User>>(`${this.baseUrl}/me`);
  }

  /** Invalida en el servidor todos los tokens del usuario. */
  logout(): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/logout`, {});
  }

  // =====================================
  // CONTACTS
  // =====================================

  getContacts(): Observable<DataResponse<Contact[]>> {
    return this.http.get<DataResponse<Contact[]>>(`${this.baseUrl}/contacts`);
  }

  addContact(data: ContactInput): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/contacts`, data);
  }

  updateContact(id: number, data: ContactInput): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contacts/${id}`, data);
  }

  deleteContact(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/contacts/${id}`);
  }

  setContactSubscription(id: number, subscribed: boolean): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/contacts/${id}/subscription`, { subscribed });
  }

  // =====================================
  // TEMPLATES
  // =====================================

  getTemplates(): Observable<DataResponse<Template[]>> {
    return this.http.get<DataResponse<Template[]>>(`${this.baseUrl}/templates`);
  }

  addTemplate(data: TemplateInput): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/templates`, data);
  }

  updateTemplate(id: number, data: TemplateInput): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.baseUrl}/templates/${id}`, data);
  }

  deleteTemplate(id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.baseUrl}/templates/${id}`);
  }

  uploadTemplateFile(formData: FormData): Observable<ApiResponse & { html: string }> {
    return this.http.post<ApiResponse & { html: string }>(`${this.baseUrl}/upload-template-file`, formData);
  }

  // =====================================
  // MAIL
  // =====================================

  sendSingleMail(data: { to: string; subject: string; body: string }): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/send-mail`, data);
  }

  /** Encola una campaña (inmediata o programada); la envía el worker del servidor. */
  sendMassiveMail(data: MassiveMailInput): Observable<MassiveMailResponse> {
    return this.http.post<MassiveMailResponse>(`${this.baseUrl}/send-massive`, data);
  }

  /** Asunto y cuerpo con las variables ya sustituidas (contacto real o datos de ejemplo). */
  previewMail(data: MailPreviewInput): Observable<DataResponse<MailPreview>> {
    return this.http.post<DataResponse<MailPreview>>(`${this.baseUrl}/mail/preview`, data);
  }

  /** Envía una copia de prueba al email del propio usuario. */
  sendTestMail(data: MailPreviewInput): Observable<ApiResponse & { sent_to: string }> {
    return this.http.post<ApiResponse & { sent_to: string }>(`${this.baseUrl}/mail/test`, data);
  }

  suggestSubjects(topic: string): Observable<ApiResponse & { subjects: string[] }> {
    return this.http.post<ApiResponse & { subjects: string[] }>(`${this.baseUrl}/ai/suggest-subject`, { topic });
  }

  // =====================================
  // HISTORY / CAMPAIGNS
  // =====================================

  getHistory(): Observable<DataResponse<Campaign[]>> {
    return this.http.get<DataResponse<Campaign[]>>(`${this.baseUrl}/history`);
  }

  getCampaignDeliveries(campaignId: number): Observable<DataResponse<Delivery[]>> {
    return this.http.get<DataResponse<Delivery[]>>(`${this.baseUrl}/history/${campaignId}/deliveries`);
  }

  cancelCampaign(campaignId: number): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(`${this.baseUrl}/history/${campaignId}/cancel`, {});
  }

  retryFailedDeliveries(campaignId: number): Observable<ApiResponse & { requeued: number }> {
    return this.http.post<ApiResponse & { requeued: number }>(`${this.baseUrl}/history/${campaignId}/retry-failed`, {});
  }

  // =====================================
  // DASHBOARD
  // =====================================

  getDashboardStats(): Observable<DataResponse<DashboardStats>> {
    return this.http.get<DataResponse<DashboardStats>>(`${this.baseUrl}/dashboard/stats`);
  }

  getDashboardSummary(): Observable<DataResponse<DashboardSummary>> {
    return this.http.get<DataResponse<DashboardSummary>>(`${this.baseUrl}/dashboard/summary`);
  }
}
