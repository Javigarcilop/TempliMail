import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private baseUrl = 'http://localhost/TempliMail/backend/api/index.php';

  constructor(private http: HttpClient) { }


  getDashboardStats(): Observable<any> {
    return this.http.get(`${this.baseUrl}/dashboard/stats`);
  }

  getResumenDashboard(): Observable<any> {
    return this.http.get(`${this.baseUrl}/dashboard/resumen`);
  }


  sendMail(data: { to: string; subject: string; body: string }) {
    const headers = new HttpHeaders({ 'Content-Type': 'application/json' });
    return this.http.post(`${this.baseUrl}/send-mail`, data, { headers });
  }


  sendMassiveMail(data: {
    contactos: number[];
    plantilla_id: number;
    fecha_programada?: string;
  }) {
    return this.http.post(`${this.baseUrl}/templates/mass-send`, data);
  }


  programarEnvio(data: {
    plantilla_id: number;
    contactos: number[];
    fecha: string;
    hora: string;
  }) {
    return this.http.post(`${this.baseUrl}/programar-envio`, data);
  }


  ejecutarCorreosProgramados() {
    return this.http.get(`${this.baseUrl}/mail/ejecutar-programados`);
  }


  getContactos(): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/contacts`);
  }

  addContacto(data: any) {
    return this.http.post(`${this.baseUrl}/contacts`, data);
  }

  deleteContacto(id: number) {
    return this.http.delete(`${this.baseUrl}/contacts/${id}`);
  }

  updateContacto(id: number, data: any) {
    return this.http.put(`${this.baseUrl}/contacts/${id}`, data);
  }


  getPlantillas(): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/templates`);
  }

  addPlantilla(data: any) {
    return this.http.post(`${this.baseUrl}/templates`, data);
  }

  updatePlantilla(id: number, data: any) {
    return this.http.put(`${this.baseUrl}/templates/${id}`, data);
  }

  deletePlantilla(id: number) {
    return this.http.delete(`${this.baseUrl}/templates/${id}`);
  }


  getHistorial(): Observable<any> {
    return this.http.get(`${this.baseUrl}/history`);
  }


  uploadTemplateFile(formData: FormData): Observable<any> {
    return this.http.post(`${this.baseUrl}/upload-template-file`, formData);
  }

  login(data: { user: string; password: string }) {
    return this.http.post(`${this.baseUrl}/login`, data);
  }
  
  register(data: { user: string; password: string }) {
    return this.http.post(`${this.baseUrl}/register`, data);
  }
  
  
}
