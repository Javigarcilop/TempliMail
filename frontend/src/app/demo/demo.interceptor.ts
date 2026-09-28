import { HttpErrorResponse, HttpInterceptorFn, HttpResponse } from '@angular/common/http';
import { delay, of, throwError } from 'rxjs';

// Backend simulado para la demo publicada: todo vive en memoria y se reinicia al recargar.
// No se envía ningún correo real ni se guarda nada en un servidor.

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

const contacts: any[] = [
  { id: 1, first_name: 'Lucía', last_name: 'Fernández', email: 'lucia.fernandez@example.com', phone: '600 111 201', company: 'Estudio Norte', position: 'Directora creativa' },
  { id: 2, first_name: 'Marcos', last_name: 'Ortega', email: 'marcos.ortega@example.com', phone: '600 111 202', company: 'Ortega & Asociados', position: 'Gerente' },
  { id: 3, first_name: 'Carmen', last_name: 'Ruiz', email: 'carmen.ruiz@example.com', phone: '600 111 203', company: 'Librería Sol', position: 'Propietaria' },
  { id: 4, first_name: 'Andrés', last_name: 'Molina', email: 'andres.molina@example.com', phone: '600 111 204', company: 'Molina Logística', position: 'Responsable de compras' },
  { id: 5, first_name: 'Elena', last_name: 'Navarro', email: 'elena.navarro@example.com', phone: '600 111 205', company: 'Café Ribera', position: 'Marketing' },
  { id: 6, first_name: 'Pablo', last_name: 'Serrano', email: 'pablo.serrano@example.com', phone: '600 111 206', company: 'Serrano Fitness', position: 'Fundador' },
  { id: 7, first_name: 'Irene', last_name: 'Castillo', email: 'irene.castillo@example.com', phone: '600 111 207', company: 'Clínica Alba', position: 'Coordinadora' },
  { id: 8, first_name: 'Diego', last_name: 'Vargas', email: 'diego.vargas@example.com', phone: '600 111 208', company: 'Vargas Motor', position: 'Director comercial' }
];

const templates: any[] = [
  {
    id: 1,
    name: 'Bienvenida',
    subject: 'Te damos la bienvenida',
    content_html: '<h2>¡Hola y bienvenido!</h2><p>Nos alegra tenerte con nosotros. Aquí encontrarás novedades, consejos y ofertas pensadas para ti.</p><p>Un saludo,<br>El equipo</p>'
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
    subject: 'Una oferta solo para ti',
    content_html: '<h2>Oferta especial</h2><p>Durante esta semana tienes un <strong>20% de descuento</strong> en todos nuestros servicios.</p><p><a href="#">Aprovechar la oferta</a></p>'
  }
];

const campaigns: any[] = [
  { id: 1, subject: 'Te damos la bienvenida', template_id: 1, template_name: 'Bienvenida', status: 'completed', sent_at: daysAgo(21), total_recipients: 3 },
  { id: 2, subject: 'Las novedades de este mes', template_id: 2, template_name: 'Newsletter mensual', status: 'completed', sent_at: daysAgo(10), total_recipients: 5 },
  { id: 3, subject: 'Una oferta solo para ti', template_id: 3, template_name: 'Oferta especial', status: 'completed', sent_at: daysAgo(4), total_recipients: 4 },
  { id: 4, subject: 'Las novedades de este mes', template_id: 2, template_name: 'Newsletter mensual', status: 'scheduled', sent_at: daysAhead(3), total_recipients: 6 }
];

const deliveries: { [campaignId: number]: any[] } = {};

const buildDeliveries = (campaignId: number, contactIds: number[], sentAt: string, failedIds: number[] = []): void => {
  deliveries[campaignId] = contactIds.map(id => {
    const c = contacts.find(x => x.id === id);
    const failed = failedIds.includes(id);
    return {
      first_name: c?.first_name ?? '',
      last_name: c?.last_name ?? '',
      email: c?.email ?? '',
      status: failed ? 'failed' : 'sent',
      sent_at: sentAt,
      error_message: failed ? 'Buzón no disponible (simulado)' : null
    };
  });
};

buildDeliveries(1, [1, 2, 3], campaigns[0].sent_at);
buildDeliveries(2, [1, 2, 3, 4, 5], campaigns[1].sent_at);
buildDeliveries(3, [2, 5, 6, 7], campaigns[2].sent_at, [7]);
buildDeliveries(4, [1, 2, 3, 4, 5, 6], campaigns[3].sent_at);
deliveries[4].forEach(d => { d.status = 'pending'; d.sent_at = null; });

let nextId = { contact: 100, template: 100, campaign: 100 };

const ok = (body: any) => of(new HttpResponse({ status: 200, body })).pipe(delay(350));
const fail = (status: number, message: string) =>
  throwError(() => new HttpErrorResponse({ status, error: { success: false, error: message } })).pipe(delay(350));

const subjectIdeas = (topic: string): string[] => {
  const t = topic.trim();
  return [
    `${t}: lo que necesitas saber`,
    `Novedades sobre ${t} que te van a interesar`,
    `¿Has visto lo último en ${t}?`,
    `${t}, ahora con una ventaja para ti`,
    `Tu guía rápida de ${t}`
  ];
};

const topBy = (counts: Map<string, number>): { name: string; total: number } | null => {
  let best: { name: string; total: number } | null = null;
  counts.forEach((total, name) => {
    if (!best || total > best.total) best = { name, total };
  });
  return best;
};

const handle = (method: string, path: string, body: any) => {
  if (path === '/login' && method === 'POST') return ok({ success: true, token: 'demo-token' });
  if (path === '/register' && method === 'POST') return ok({ success: true });

  // CONTACTS
  if (path === '/contacts' && method === 'GET') return ok({ success: true, data: [...contacts] });
  if (path === '/contacts' && method === 'POST') {
    if (!body?.email) return fail(400, 'El email es obligatorio.');
    if (contacts.some(c => c.email.toLowerCase() === String(body.email).toLowerCase())) {
      return fail(409, 'Ya existe un contacto con ese email.');
    }
    const contact = { id: nextId.contact++, ...body };
    contacts.push(contact);
    return ok({ success: true, id: contact.id });
  }
  let m = path.match(/^\/contacts\/(\d+)$/);
  if (m && method === 'PUT') {
    const c = contacts.find(x => x.id === +m![1]);
    if (!c) return fail(404, 'Contacto no encontrado.');
    Object.assign(c, body);
    return ok({ success: true });
  }
  if (m && method === 'DELETE') {
    const i = contacts.findIndex(x => x.id === +m![1]);
    if (i >= 0) contacts.splice(i, 1);
    return ok({ success: true });
  }

  // TEMPLATES
  if (path === '/templates' && method === 'GET') return ok({ success: true, data: [...templates] });
  if (path === '/templates' && method === 'POST') {
    const template = { ...body, id: nextId.template++ };
    templates.push(template);
    return ok({ success: true, id: template.id });
  }
  m = path.match(/^\/templates\/(\d+)$/);
  if (m && method === 'PUT') {
    const t = templates.find(x => x.id === +m![1]);
    if (!t) return fail(404, 'Plantilla no encontrada.');
    Object.assign(t, body, { id: t.id });
    return ok({ success: true });
  }
  if (m && method === 'DELETE') {
    const i = templates.findIndex(x => x.id === +m![1]);
    if (i >= 0) templates.splice(i, 1);
    return ok({ success: true });
  }
  if (path === '/upload-template-file' && method === 'POST') {
    return ok({
      success: true,
      html: '<h2>Plantilla importada</h2><p>En la demo no se procesan archivos .docx ni .pdf. En la versión completa, el contenido del archivo se convierte a HTML y se carga aquí para editarlo.</p>'
    });
  }

  // ENVÍOS
  if (path === '/send-mail' && method === 'POST') {
    const id = nextId.campaign++;
    const sentAt = new Date().toISOString();
    campaigns.push({ id, subject: body?.subject ?? '', template_id: null, template_name: null, status: 'completed', sent_at: sentAt, total_recipients: 1 });
    deliveries[id] = [{ first_name: '', last_name: '', email: body?.to ?? '', status: 'sent', sent_at: sentAt, error_message: null }];
    return ok({ success: true });
  }
  if (path === '/send-massive' && method === 'POST') {
    const template = templates.find(t => t.id === +body?.template_id);
    const ids: number[] = body?.contact_ids ?? [];
    const scheduled = !!body?.scheduled_at;
    const id = nextId.campaign++;
    const sentAt = scheduled ? new Date(body.scheduled_at).toISOString() : new Date().toISOString();
    campaigns.push({
      id,
      subject: template?.subject ?? '',
      template_id: template?.id ?? null,
      template_name: template?.name ?? null,
      status: scheduled ? 'scheduled' : 'completed',
      sent_at: sentAt,
      total_recipients: ids.length
    });
    buildDeliveries(id, ids, sentAt);
    if (scheduled) deliveries[id].forEach(d => { d.status = 'pending'; d.sent_at = null; });
    return ok({ success: true, campaign_id: id });
  }
  if (path === '/process-scheduled' && method === 'GET') return ok({ success: true, processed: 0 });
  if (path === '/history' && method === 'GET') {
    const sorted = [...campaigns].sort((a, b) => +new Date(b.sent_at) - +new Date(a.sent_at));
    return ok({ success: true, data: sorted });
  }
  m = path.match(/^\/history\/(\d+)\/deliveries$/);
  if (m && method === 'GET') return ok({ success: true, data: deliveries[+m[1]] ?? [] });

  // IA (simulada)
  if (path === '/ai/suggest-subject' && method === 'POST') {
    if (!body?.topic) return fail(400, 'Indica un tema.');
    return ok({ success: true, subjects: subjectIdeas(body.topic) });
  }

  // DASHBOARD
  if (path === '/dashboard/stats' && method === 'GET') {
    return ok({
      success: true,
      data: { total_campaigns: campaigns.length, total_contacts: contacts.length, total_templates: templates.length }
    });
  }
  if (path === '/dashboard/summary' && method === 'GET') {
    const byTemplate = new Map<string, number>();
    campaigns.forEach(c => { if (c.template_name) byTemplate.set(c.template_name, (byTemplate.get(c.template_name) ?? 0) + 1); });
    const byContact = new Map<string, number>();
    Object.values(deliveries).forEach(list => list.forEach(d => {
      const name = `${d.first_name} ${d.last_name}`.trim();
      if (name) byContact.set(name, (byContact.get(name) ?? 0) + 1);
    }));
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
