import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ApiService {

  private baseUrl = 'http://localhost/TempliMail/backend/api/index.php';

  constructor(private http: HttpClient) {}

  // =====================================================
  // AUTH
  // =====================================================

  login(data: { username: string; password: string }): Observable<any> {
    return this.http.post(`${this.baseUrl}/login`, data);
  }

  register(data: { username: string; email: string; password: string }): Observable<any> {
    return this.http.post(`${this.baseUrl}/register`, data);
  }

  // =====================================================
  // CONTACTS
  // =====================================================

  getContacts(): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/contacts`);
  }

  addContact(data: {
    first_name?: string;
    last_name?: string;
    email: string;
    phone?: string;
    company?: string;
    position?: string;
  }): Observable<any> {
    return this.http.post(`${this.baseUrl}/contacts`, data);
  }

  updateContact(id: number, data: any): Observable<any> {
    return this.http.put(`${this.baseUrl}/contacts/${id}`, data);
  }

  deleteContact(id: number): Observable<any> {
    return this.http.delete(`${this.baseUrl}/contacts/${id}`);
  }

  // =====================================================
  // TEMPLATES
  // =====================================================

  getTemplates(): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/templates`);
  }

  addTemplate(data: {
    name: string;
    subject: string;
    content_html: string;
  }): Observable<any> {
    return this.http.post(`${this.baseUrl}/templates`, data);
  }

  updateTemplate(id: number, data: any): Observable<any> {
    return this.http.put(`${this.baseUrl}/templates/${id}`, data);
  }

  deleteTemplate(id: number): Observable<any> {
    return this.http.delete(`${this.baseUrl}/templates/${id}`);
  }

  uploadTemplateFile(formData: FormData): Observable<any> {
    return this.http.post(`${this.baseUrl}/upload-template-file`, formData);
  }

  // =====================================================
  // EMAIL CAMPAIGNS
  // =====================================================

  sendSingleMail(data: {
    to: string;
    subject: string;
    body: string;
  }): Observable<any> {
    const headers = new HttpHeaders({ 'Content-Type': 'application/json' });
    return this.http.post(`${this.baseUrl}/send-mail`, data, { headers });
  }

  sendMassiveMail(data: {
    template_id: number;
    contact_ids: number[];
    scheduled_at?: string;
  }): Observable<any> {
    return this.http.post(`${this.baseUrl}/send-massive`, data);
  }

  getHistory(): Observable<any> {
    return this.http.get(`${this.baseUrl}/history`);
  }

  processScheduledCampaigns(): Observable<any> {
    return this.http.get(`${this.baseUrl}/process-scheduled`);
  }

  getDashboardStats(): Observable<any> {
    return this.http.get(`${this.baseUrl}/dashboard/stats`);
  }

  getDashboardSummary(): Observable<any> {
    return this.http.get(`${this.baseUrl}/dashboard/summary`);
  }
}
