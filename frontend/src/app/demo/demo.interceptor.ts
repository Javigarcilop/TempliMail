import { HttpErrorResponse, HttpInterceptorFn, HttpResponse } from '@angular/common/http';
import { delay, of, throwError } from 'rxjs';

// Backend simulado para la demo publicada: todo vive en memoria y se reinicia al recargar.
// No se envía ningún correo real ni se guarda nada en un servidor.
// Reproduce el contrato de la API real (ver models/api.models.ts).

const daysAgo = (days: number, hour = 10): string => {
  const d = new Date();
  d.setDate(d.getDate() - days);
  d.setHours(hour, 0, 0, 0);
  return d.toISOString();
};

const daysAhead = (days: number, hour = 10): string => {
  const d = new Date();
  d.setDate(d.getDate() + days);
  d.setHours(hour, 0, 0, 0);
  return d.toISOString();
};

/** JWT de mentira (sin firma válida) con caducidad lejana: basta para pasar los guards. */
const demoToken = (): string => {
  const b64 = (o: object) => btoa(JSON.stringify(o)).replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');

  return `${b64({ alg: 'none', typ: 'JWT' })}.${b64({ sub: 1, exp: Math.floor(Date.now() / 1000) + 86400 })}.demo`;
};

const contacts: any[] = [
  { id: 1, first_name: 'Lucía', last_name: 'Fernández', email: 'lucia.fernandez@example.com', phone: '600 111 201', company: 'Estudio Norte', position: 'Directora creativa' },
  { id: 2, first_name: 'Marcos', last_name: 'Ortega', email: 'marcos.ortega@example.com', phone: '600 111 202', company: 'Ortega & Asociados', position: 'Gerente' },
  { id: 3, first_name: 'Carmen', last_name: 'Ruiz', email: 'carmen.ruiz@example.com', phone: '600 111 203', company: 'Librería Sol', position: 'Propietaria' },
  { id: 4, first_name: 'Andrés', last_name: 'Molina', email: 'andres.molina@example.com', phone: '600 111 204', company: 'Molina Logística', position: 'Responsable de compras' },
  { id: 5, first_name: 'Elena', last_name: 'Navarro', email: 'elena.navarro@example.com', phone: '600 111 205', company: 'Café Ribera', position: 'Marketing' },
  { id: 6, first_name: 'Pablo', last_name: 'Serrano', email: 'pablo.serrano@example.com', phone: '600 111 206', company: 'Serrano Fitness', position: 'Fundador' },
  { id: 7, first_name: 'Irene', last_name: 'Castillo', email: 'irene.castillo@example.com', phone: '600 111 207', company: 'Clínica Alba', position: 'Coordinadora' },
  { id: 8, first_name: 'Diego', last_name: 'Vargas', email: 'diego.vargas@example.com', phone: '600 111 208', company: 'Vargas Motor', position: 'Director comercial', unsubscribed_at: daysAgo(2) }
].map(c => ({ unsubscribed_at: null, created_at: daysAgo(30), ...c }));

const templates: any[] = [
  {
    id: 1,
    name: 'Bienvenida',
    subject: 'Te damos la bienvenida, {{first_name|amigo}}',
    content_html: '<h2>¡Hola {{first_name|y bienvenido}}!</h2><p>Nos alegra tenerte con nosotros. Aquí encontrarás novedades, consejos y ofertas pensadas para ti.</p><p>Un saludo,<br>El equipo</p>'
  },
  {
    id: 2,
    name: 'Newsletter mensual',
    subject: 'Las novedades de este mes',
    content_html: '<h2>Novedades del mes</h2><ul><li>Nuevas funcionalidades en la plataforma</li><li>Guía práctica de email marketing</li><li>Próximos eventos</li></ul><p>Gracias por leernos.</p>'
  },
  {
    id: 3,
    name: 'Oferta especial',
    subject: 'Una oferta solo para ti, {{first_name|amigo}}',
    content_html: '<h2>Oferta especial para {{company|tu empresa}}</h2><p>Durante esta semana tienes un <strong>20% de descuento</strong> en todos nuestros servicios.</p><p><a href="#">Aprovechar la oferta</a></p>'
  }
].map(t => ({ created_at: daysAgo(40), updated_at: daysAgo(40), ...t }));

type DemoDelivery = {
  id: number; status: string; retry_count: number; sent_at: string | null;
  error_message: string | null; first_name: string | null; last_name: string | null; email: string;
};

const campaigns: any[] = [
  { id: 1, name: 'Bienvenida clientes nuevos', type: 'mass', subject: 'Te damos la bienvenida', template_id: 1, template_name: 'Bienvenida', status: 'completed', scheduled_at: null, created_at: daysAgo(21) },
  { id: 2, name: null, type: 'mass', subject: 'Las novedades de este mes', template_id: 2, template_name: 'Newsletter mensual', status: 'completed', scheduled_at: null, created_at: daysAgo(10) },
  { id: 3, name: 'Promo de otoño', type: 'mass', subject: 'Una oferta solo para ti', template_id: 3, template_name: 'Oferta especial', status: 'completed', scheduled_at: null, created_at: daysAgo(4) },
  { id: 4, name: 'Newsletter de la semana que viene', type: 'mass', subject: 'Las novedades de este mes', template_id: 2, template_name: 'Newsletter mensual', status: 'scheduled', scheduled_at: daysAhead(3), created_at: daysAgo(1) }
].map(c => ({ updated_at: c.created_at, ...c }));

const deliveries: { [campaignId: number]: DemoDelivery[] } = {};
let nextDeliveryId = 1;

const buildDeliveries = (campaignId: number, contactIds: number[], sentAt: string | null, failedIds: number[] = []): void => {
  deliveries[campaignId] = contactIds.map(id => {
    const c = contacts.find(x => x.id === id);
    const failed = failedIds.includes(id);
    return {
      id: nextDeliveryId++,
      status: sentAt === null ? 'pending' : failed ? 'failed' : 'sent',
      retry_count: failed ? 3 : 0,
      sent_at: sentAt !== null && !failed ? sentAt : null,
      error_message: failed ? 'Buzón no disponible (simulado)' : null,
      first_name: c?.first_name ?? '',
      last_name: c?.last_name ?? '',
      email: c?.email ?? ''
    };
  });
};

buildDeliveries(1, [1, 2, 3], campaigns[0].created_at);
buildDeliveries(2, [1, 2, 3, 4, 5], campaigns[1].created_at);
buildDeliveries(3, [2, 5, 6, 7], campaigns[2].created_at, [7]);
buildDeliveries(4, [1, 2, 3, 4, 5, 6], null);

let nextId = { contact: 100, template: 100, campaign: 100 };

const ok = (body: any) => of(new HttpResponse({ status: 200, body })).pipe(delay(350));
const fail = (status: number, message: string) =>
  throwError(() => new HttpErrorResponse({ status, error: { success: false, error: message } })).pipe(delay(350));

const count = (campaignId: number, status: string): number =>
  (deliveries[campaignId] ?? []).filter(d => d.status === status).length;

/** Campaña tal y como la devuelve GET /history (con contadores por estado). */
const summarize = (c: any) => ({
  ...c,
  total_recipients: (deliveries[c.id] ?? []).length,
  sent: count(c.id, 'sent'),
  failed: count(c.id, 'failed'),
  pending: count(c.id, 'pending'),
  skipped: count(c.id, 'skipped')
});

// ------- Variables de plantilla ({{first_name|valor por defecto}}) -------

const SAMPLE = { first_name: 'Ana', last_name: 'García', company: 'Empresa Ejemplo', position: 'Directora de Marketing', email: 'ana.garcia@ejemplo.com' };
const KNOWN = ['first_name', 'last_name', 'full_name', 'email', 'company', 'position', 'unsubscribe_url'];
const FOOTER = '<hr><p style="font-size:12px;color:#888;text-align:center">Si no deseas recibir más correos, <a href="{{unsubscribe_url}}">date de baja aquí</a>.</p>';

const render = (text: string, vars: Record<string, any>): string => {
  const data: Record<string, any> = { ...vars, full_name: `${vars['first_name'] ?? ''} ${vars['last_name'] ?? ''}`.trim() };

  return text.replace(/\{\{\s*([a-z_]+)\s*(?:\|([^}]*))?\}\}/gi, (whole, key: string, fallback?: string) => {
    const k = key.toLowerCase();
    if (!KNOWN.includes(k)) return whole;
    return String(data[k] ?? '').trim() || (fallback ?? '').trim();
  });
};

const subjectIdeas = (topic: string): string[] => {
  const t = topic.trim();
  return [
    `${t}: lo que necesitas saber`,
    `Novedades sobre ${t} que te van a interesar`,
    `¿Has visto lo último en ${t}?`
  ];
};

const topBy = (counts: Map<string, number>): { name: string; total: number } | null => {
  let best: { name: string; total: number } | null = null;
  counts.forEach((total, name) => {
    if (!best || total > best.total) best = { name, total };
  });
  return best;
};

/** Simula el worker: una campaña en cola pasa a "completada" a los pocos segundos. */
const simulateWorker = (campaignId: number): void => {
  setTimeout(() => {
    const campaign = campaigns.find(c => c.id === campaignId);
    if (!campaign || campaign.status !== 'scheduled') return;

    campaign.status = 'processing';

    setTimeout(() => {
      const now = new Date().toISOString();
      (deliveries[campaignId] ?? []).forEach(d => {
        if (d.status === 'pending') { d.status = 'sent'; d.sent_at = now; }
      });
      campaign.status = 'completed';
      campaign.updated_at = now;
    }, 2500);
  }, 1500);
};

const handle = (method: string, path: string, body: any) => {
  if (path === '/login' && method === 'POST') return ok({ success: true, token: demoToken() });
  if (path === '/register' && method === 'POST') return ok({ success: true });
  if (path === '/me' && method === 'GET') return ok({ success: true, data: { id: 1, username: 'demo', email: 'demo@example.com' } });
  if (path === '/logout' && method === 'POST') return ok({ success: true });

  // CONTACTS
  if (path === '/contacts' && method === 'GET') return ok({ success: true, data: [...contacts].reverse() });
  if (path === '/contacts' && method === 'POST') {
    if (!body?.email) return fail(400, 'El email es obligatorio');
    if (contacts.some(c => c.email.toLowerCase() === String(body.email).toLowerCase())) {
      return fail(409, 'Ya existe un contacto con ese email');
    }
    const contact = { unsubscribed_at: null, created_at: new Date().toISOString(), ...body, id: nextId.contact++ };
    contacts.push(contact);
    return ok({ success: true });
  }
  let m = path.match(/^\/contacts\/(\d+)$/);
  if (m && method === 'PUT') {
    const c = contacts.find(x => x.id === +m![1]);
    if (!c) return fail(404, 'Contacto no encontrado');
    Object.assign(c, body, { id: c.id, unsubscribed_at: c.unsubscribed_at });
    return ok({ success: true });
  }
  if (m && method === 'DELETE') {
    const i = contacts.findIndex(x => x.id === +m![1]);
    if (i < 0) return fail(404, 'Contacto no encontrado');
    contacts.splice(i, 1);
    return ok({ success: true });
  }
  m = path.match(/^\/contacts\/(\d+)\/subscription$/);
  if (m && method === 'PUT') {
    const c = contacts.find(x => x.id === +m![1]);
    if (!c) return fail(404, 'Contacto no encontrado');
    c.unsubscribed_at = body?.subscribed ? null : new Date().toISOString();
    return ok({ success: true });
  }

  // TEMPLATES
  if (path === '/templates' && method === 'GET') return ok({ success: true, data: [...templates].reverse() });
  if (path === '/templates' && method === 'POST') {
    if (!body?.name || !body?.subject) return fail(400, 'Nombre, asunto y contenido son obligatorios');
    const now = new Date().toISOString();
    templates.push({ name: body.name, subject: body.subject, content_html: body.content_html ?? '', created_at: now, updated_at: now, id: nextId.template++ });
    return ok({ success: true });
  }
  m = path.match(/^\/templates\/(\d+)$/);
  if (m && method === 'PUT') {
    const t = templates.find(x => x.id === +m![1]);
    if (!t) return fail(404, 'Plantilla no encontrada');
    Object.assign(t, { name: body.name, subject: body.subject, content_html: body.content_html, updated_at: new Date().toISOString() });
    return ok({ success: true });
  }
  if (m && method === 'DELETE') {
    const i = templates.findIndex(x => x.id === +m![1]);
    if (i < 0) return fail(404, 'Plantilla no encontrada');
    templates.splice(i, 1);
    return ok({ success: true });
  }
  if (path === '/upload-template-file' && method === 'POST') {
    return ok({
      success: true,
      html: '<h2>Plantilla importada</h2><p>En la demo no se procesan archivos .docx ni .pdf. En la versión completa, el contenido del archivo se convierte a HTML y se carga aquí para editarlo.</p>'
    });
  }

  // VISTA PREVIA Y PRUEBA
  if (path === '/mail/preview' && method === 'POST') {
    const contact = contacts.find(c => c.id === +body?.contact_id);
    const vars: Record<string, any> = { ...(contact ?? SAMPLE), unsubscribe_url: '#' };
    const html = String(body?.content_html ?? '');
    return ok({
      success: true,
      data: {
        subject: render(String(body?.subject ?? ''), vars),
        body: render(html.includes('{{unsubscribe_url') ? html : html + FOOTER, vars)
      }
    });
  }
  if (path === '/mail/test' && method === 'POST') return ok({ success: true, sent_to: 'demo@example.com (simulado)' });

  // ENVÍOS
  if (path === '/send-mail' && method === 'POST') {
    if (!body?.to || !body?.subject) return fail(400, 'Destinatario, asunto y mensaje son obligatorios');
    const id = nextId.campaign++;
    const now = new Date().toISOString();
    campaigns.push({ id, name: null, type: 'single', subject: body.subject, template_id: null, template_name: null, status: 'completed', scheduled_at: null, created_at: now, updated_at: now });
    deliveries[id] = [{ id: nextDeliveryId++, status: 'sent', retry_count: 0, sent_at: now, error_message: null, first_name: '', last_name: '', email: body.to }];
    return ok({ success: true });
  }
  if (path === '/send-massive' && method === 'POST') {
    const template = templates.find(t => t.id === +body?.template_id);
    if (!template) return fail(404, 'Plantilla no encontrada');

    const requested: number[] = [...new Set<number>(body?.contact_ids ?? [])];
    const valid = requested.filter(cid => contacts.some(c => c.id === cid && !c.unsubscribed_at));
    if (valid.length === 0) return fail(400, 'Ninguno de los contactos seleccionados puede recibir correos');

    const scheduled = body?.scheduled_at && new Date(body.scheduled_at).getTime() > Date.now();
    const id = nextId.campaign++;
    const now = new Date().toISOString();
    campaigns.push({
      id,
      name: body?.name ?? null,
      type: 'mass',
      subject: template.subject,
      template_id: template.id,
      template_name: template.name,
      status: 'scheduled',
      scheduled_at: scheduled ? new Date(body.scheduled_at).toISOString() : null,
      created_at: now,
      updated_at: now
    });
    buildDeliveries(id, valid, null);
    if (!scheduled) simulateWorker(id);

    return of(new HttpResponse({
      status: 201,
      body: { success: true, campaign_id: id, scheduled: !!scheduled, recipients: valid.length, excluded: requested.length - valid.length }
    })).pipe(delay(350));
  }
  if (path === '/process-scheduled' && method === 'GET') return ok({ success: true, processed: 0 });
  if (path === '/history' && method === 'GET') {
    const sorted = [...campaigns].sort((a, b) => +new Date(b.created_at) - +new Date(a.created_at));
    return ok({ success: true, data: sorted.map(summarize) });
  }
  m = path.match(/^\/history\/(\d+)\/deliveries$/);
  if (m && method === 'GET') return ok({ success: true, data: deliveries[+m[1]] ?? [] });
  m = path.match(/^\/history\/(\d+)\/cancel$/);
  if (m && method === 'POST') {
    const c = campaigns.find(x => x.id === +m![1]);
    if (!c || c.status !== 'scheduled') return fail(409, 'Solo se pueden cancelar campañas programadas que no hayan empezado');
    c.status = 'cancelled';
    (deliveries[c.id] ?? []).forEach(d => { if (d.status === 'pending') { d.status = 'skipped'; d.error_message = 'Campaña cancelada'; } });
    return ok({ success: true });
  }
  m = path.match(/^\/history\/(\d+)\/retry-failed$/);
  if (m && method === 'POST') {
    const c = campaigns.find(x => x.id === +m![1]);
    const failed = (deliveries[+m![1]] ?? []).filter(d => d.status === 'failed');
    if (!c) return fail(404, 'Campaña no encontrada');
    if (c.status !== 'completed' || failed.length === 0) return fail(409, 'La campaña no tiene entregas fallidas');
    failed.forEach(d => { d.status = 'pending'; d.error_message = null; d.retry_count = 0; });
    c.status = 'scheduled';
    simulateWorker(c.id);
    return ok({ success: true, requeued: failed.length });
  }

  // IA (simulada)
  if (path === '/ai/suggest-subject' && method === 'POST') {
    if (!body?.topic) return fail(400, 'Indica de qué trata el correo');
    return ok({ success: true, subjects: subjectIdeas(body.topic) });
  }

  // DASHBOARD
  if (path === '/dashboard/stats' && method === 'GET') {
    return ok({
      success: true,
      data: { total_campaigns: campaigns.filter(c => c.type === 'mass').length, total_contacts: contacts.length, total_templates: templates.length }
    });
  }
  if (path === '/dashboard/summary' && method === 'GET') {
    const byTemplate = new Map<string, number>();
    campaigns.filter(c => c.type === 'mass').forEach(c => {
      if (c.template_name) byTemplate.set(c.template_name, (byTemplate.get(c.template_name) ?? 0) + 1);
    });
    const byContact = new Map<string, number>();
    Object.entries(deliveries).forEach(([cid, list]) => {
      if (campaigns.find(c => c.id === +cid)?.type !== 'mass') return;
      list.filter(d => d.status === 'sent').forEach(d => {
        const name = `${d.first_name ?? ''} ${d.last_name ?? ''}`.trim() || d.email;
        byContact.set(name, (byContact.get(name) ?? 0) + 1);
      });
    });
    return ok({ success: true, data: { top_template: topBy(byTemplate), top_contact: topBy(byContact) } });
  }

  return fail(404, 'Ruta no disponible en la demo.');
};

export const demoInterceptor: HttpInterceptorFn = (req) => {
  const marker = 'index.php';
  const at = req.url.indexOf(marker);
  const path = (at >= 0 ? req.url.slice(at + marker.length) : req.url).replace(/\/$/, '');
  return handle(req.method, path, req.body);
};
