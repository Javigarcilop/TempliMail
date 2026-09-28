export interface ApiResponse {
  success: boolean;
  error?: string;
}

export interface DataResponse<T> extends ApiResponse {
  data: T;
}

export interface User {
  id: number;
  username: string;
  email: string;
}

export interface Contact {
  id: number;
  first_name: string | null;
  last_name: string | null;
  email: string;
  phone: string | null;
  company: string | null;
  position: string | null;
  /** ISO 8601 (UTC) si el contacto se dio de baja */
  unsubscribed_at: string | null;
  created_at: string;
  group_ids: number[];
}

export interface Group {
  id: number;
  name: string;
  member_count: number;
}

export interface ImportResult {
  created: number;
  duplicates: number;
  invalid: { row: number; email: string; reason: string }[];
}

export interface ContactInput {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  company: string;
  position: string;
}

export interface Template {
  id: number;
  name: string;
  subject: string;
  content_html: string;
  created_at: string;
  updated_at: string;
}

export interface TemplateInput {
  id?: number | null;
  name: string;
  subject: string;
  content_html: string;
}

export type CampaignStatus = 'scheduled' | 'processing' | 'completed' | 'cancelled';

export interface Campaign {
  id: number;
  name: string | null;
  type: 'mass' | 'single';
  subject: string;
  status: CampaignStatus;
  /** ISO 8601 (UTC). null = en cola para enviarse ya */
  scheduled_at: string | null;
  created_at: string;
  updated_at: string;
  template_name: string | null;
  total_recipients: number;
  sent: number;
  failed: number;
  pending: number;
  skipped: number;
}

export type DeliveryStatus = 'pending' | 'sent' | 'failed' | 'skipped';

export interface Delivery {
  id: number;
  status: DeliveryStatus;
  retry_count: number;
  sent_at: string | null;
  error_message: string | null;
  first_name: string | null;
  last_name: string | null;
  email: string;
}

export interface MassiveMailInput {
  template_id: number;
  contact_ids: number[];
  name?: string;
  /** ISO 8601 con zona horaria (p. ej. new Date(...).toISOString()) */
  scheduled_at?: string;
}

export interface MassiveMailResponse extends ApiResponse {
  campaign_id: number;
  scheduled: boolean;
  recipients: number;
  excluded: number;
}

export interface MailPreviewInput {
  subject: string;
  content_html: string;
  contact_id?: number;
}

export interface MailPreview {
  subject: string;
  body: string;
}

export interface DashboardStats {
  total_campaigns: number;
  total_contacts: number;
  total_templates: number;
  total_sent: number;
  total_failed: number;
}

export interface ActivityDay {
  /** YYYY-MM-DD (UTC) */
  date: string;
  sent: number;
}

export interface DashboardSummary {
  top_template: { name: string; total: number } | null;
  top_contact: { name: string; total: number } | null;
}

/** Variables que se pueden usar en plantillas: {{first_name}}, {{first_name|amigo}}... */
export const TEMPLATE_VARIABLES: { key: string; label: string }[] = [
  { key: 'first_name', label: 'Nombre' },
  { key: 'last_name', label: 'Apellidos' },
  { key: 'full_name', label: 'Nombre completo' },
  { key: 'email', label: 'Email' },
  { key: 'company', label: 'Empresa' },
  { key: 'position', label: 'Cargo' },
  { key: 'unsubscribe_url', label: 'Enlace de baja' }
];
