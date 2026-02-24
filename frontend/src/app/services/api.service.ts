import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ApiService {

  private baseUrl = 'http://localhost/TempliMail/backend/api/index.php';

  constructor(private http: HttpClient) {}

  // =====================================
  // 🔐 Helper para enviar JWT
  // =====================================

  private getAuthHeaders(): HttpHeaders {
    const token = localStorage.getItem('token');

    return new HttpHeaders({
      'Authorization': `Bearer ${token ?? ''}`
    });
  }

  // =====================================
  // AUTH
  // =====================================

  login(data: { username: string; password: string }): Observable<any> {
    return this.http.post(`${this.baseUrl}/login`, data);
  }

  register(data: { username: string; email: string; password: string }): Observable<any> {
    return this.http.post(`${this.baseUrl}/register`, data);
  }

  // =====================================
  // CONTACTS
  // =====================================

  getContacts(): Observable<any[]> {
    return this.http.get<any[]>(
      `${this.baseUrl}/contacts`,
      { headers: this.getAuthHeaders() }
    );
  }

  addContact(data: any): Observable<any> {
    return this.http.post(
      `${this.baseUrl}/contacts`,
      data,
      { headers: this.getAuthHeaders() }
    );
  }

  updateContact(id: number, data: any): Observable<any> {
    return this.http.put(
      `${this.baseUrl}/contacts/${id}`,
      data,
      { headers: this.getAuthHeaders() }
    );
  }

  deleteContact(id: number): Observable<any> {
    return this.http.delete(
      `${this.baseUrl}/contacts/${id}`,
      { headers: this.getAuthHeaders() }
    );
  }

  // =====================================
  // TEMPLATES
  // =====================================

  getTemplates(): Observable<any[]> {
    return this.http.get<any[]>(
      `${this.baseUrl}/templates`,
      { headers: this.getAuthHeaders() }
    );
  }

  addTemplate(data: any): Observable<any> {
    return this.http.post(
      `${this.baseUrl}/templates`,
      data,
      { headers: this.getAuthHeaders() }
    );
  }

  updateTemplate(id: number, data: any): Observable<any> {
    return this.http.put(
      `${this.baseUrl}/templates/${id}`,
      data,
      { headers: this.getAuthHeaders() }
    );
  }

  deleteTemplate(id: number): Observable<any> {
    return this.http.delete(
      `${this.baseUrl}/templates/${id}`,
      { headers: this.getAuthHeaders() }
    );
  }

  uploadTemplateFile(formData: FormData): Observable<any> {
    return this.http.post(
      `${this.baseUrl}/upload-template-file`,
      formData,
      { headers: this.getAuthHeaders() }
    );
  }

  // =====================================
  // EMAIL CAMPAIGNS
  // =====================================

  sendSingleMail(data: any): Observable<any> {
    return this.http.post(
      `${this.baseUrl}/send-mail`,
      data,
      { headers: this.getAuthHeaders() }
    );
  }

  sendMassiveMail(data: any): Observable<any> {
    return this.http.post(
      `${this.baseUrl}/send-massive`,
      data,
      { headers: this.getAuthHeaders() }
    );
  }

  getHistory(): Observable<any> {
    return this.http.get(
      `${this.baseUrl}/history`,
      { headers: this.getAuthHeaders() }
    );
  }

  processScheduledCampaigns(): Observable<any> {
    return this.http.get(
      `${this.baseUrl}/process-scheduled`,
      { headers: this.getAuthHeaders() }
    );
  }

  getDashboardStats(): Observable<any> {
    return this.http.get(
      `${this.baseUrl}/dashboard/stats`,
      { headers: this.getAuthHeaders() }
    );
  }

  getDashboardSummary(): Observable<any> {
    return this.http.get(
      `${this.baseUrl}/dashboard/summary`,
      { headers: this.getAuthHeaders() }
    );
  }
}