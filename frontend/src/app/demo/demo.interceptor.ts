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

const contactos: any[] = [
  { id: 1, nombre: 'Lucía', apellidos: 'Fernández', correo: 'lucia.fernandez@example.com', telefono: '600 111 201', empresa: 'Estudio Norte', cargo: 'Directora creativa' },
  { id: 2, nombre: 'Marcos', apellidos: 'Ortega', correo: 'marcos.ortega@example.com', telefono: '600 111 202', empresa: 'Ortega & Asociados', cargo: 'Gerente' },
  { id: 3, nombre: 'Carmen', apellidos: 'Ruiz', correo: 'carmen.ruiz@example.com', telefono: '600 111 203', empresa: 'Librería Sol', cargo: 'Propietaria' },
  { id: 4, nombre: 'Andrés', apellidos: 'Molina', correo: 'andres.molina@example.com', telefono: '600 111 204', empresa: 'Molina Logística', cargo: 'Responsable de compras' },
  { id: 5, nombre: 'Elena', apellidos: 'Navarro', correo: 'elena.navarro@example.com', telefono: '600 111 205', empresa: 'Café Ribera', cargo: 'Marketing' },
  { id: 6, nombre: 'Pablo', apellidos: 'Serrano', correo: 'pablo.serrano@example.com', telefono: '600 111 206', empresa: 'Serrano Fitness', cargo: 'Fundador' },
  { id: 7, nombre: 'Irene', apellidos: 'Castillo', correo: 'irene.castillo@example.com', telefono: '600 111 207', empresa: 'Clínica Alba', cargo: 'Coordinadora' },
  { id: 8, nombre: 'Diego', apellidos: 'Vargas', correo: 'diego.vargas@example.com', telefono: '600 111 208', empresa: 'Vargas Motor', cargo: 'Director comercial', baja_en: daysAgo(2) }
].map(c => ({ baja_en: null, creado_en: daysAgo(30), ids_grupo: [] as number[], ...c }));

const grupos: { id: number; nombre: string }[] = [
  { id: 1, nombre: 'Clientes' },
  { id: 2, nombre: 'Newsletter' }
];
[1, 2, 3, 4].forEach(id => contactos.find(c => c.id === id)!.ids_grupo.push(1));
[1, 2, 3, 4, 5, 6].forEach(id => contactos.find(c => c.id === id)!.ids_grupo.push(2));

let cuenta = { id: 1, nombre_usuario: 'demo', correo: 'demo@example.com' };
let contrasenaCuenta = 'demo1234';

const plantillas: any[] = [
  {
    id: 1,
    nombre: 'Bienvenida',
    asunto: 'Te damos la bienvenida, {{nombre|amigo}}',
    contenido_html: '<h2>¡Hola {{nombre|y bienvenido}}!</h2><p>Nos alegra tenerte con nosotros. Aquí encontrarás novedades, consejos y ofertas pensadas para ti.</p><p>Un saludo,<br>El equipo</p>'
  },
  {
    id: 2,
    nombre: 'Newsletter mensual',
    asunto: 'Las novedades de este mes',
    contenido_html: '<h2>Novedades del mes</h2><ul><li>Nuevas funcionalidades en la plataforma</li><li>Guía práctica de email marketing</li><li>Próximos eventos</li></ul><p>Gracias por leernos.</p>'
  },
  {
    id: 3,
    nombre: 'Oferta especial',
    asunto: 'Una oferta solo para ti, {{nombre|amigo}}',
    contenido_html: '<h2>Oferta especial para {{empresa|tu empresa}}</h2><p>Durante esta semana tienes un <strong>20% de descuento</strong> en todos nuestros servicios.</p><p><a href="#">Aprovechar la oferta</a></p>'
  }
].map(t => ({ creado_en: daysAgo(40), actualizado_en: daysAgo(40), ...t }));

type EntregaDemo = {
  id: number; estado: string; reintentos: number; enviado_en: string | null;
  mensaje_error: string | null; nombre: string | null; apellidos: string | null; correo: string;
};

const campanas: any[] = [
  { id: 1, nombre: 'Bienvenida clientes nuevos', tipo: 'mass', asunto: 'Te damos la bienvenida', plantilla_id: 1, nombre_plantilla: 'Bienvenida', estado: 'completed', programado_en: null, creado_en: daysAgo(21) },
  { id: 2, nombre: null, tipo: 'mass', asunto: 'Las novedades de este mes', plantilla_id: 2, nombre_plantilla: 'Newsletter mensual', estado: 'completed', programado_en: null, creado_en: daysAgo(10) },
  { id: 3, nombre: 'Promo de otoño', tipo: 'mass', asunto: 'Una oferta solo para ti', plantilla_id: 3, nombre_plantilla: 'Oferta especial', estado: 'completed', programado_en: null, creado_en: daysAgo(4) },
  { id: 4, nombre: 'Newsletter de la semana que viene', tipo: 'mass', asunto: 'Las novedades de este mes', plantilla_id: 2, nombre_plantilla: 'Newsletter mensual', estado: 'scheduled', programado_en: daysAhead(3), creado_en: daysAgo(1) }
].map(c => ({ actualizado_en: c.creado_en, ...c }));

const entregas: { [campanaId: number]: EntregaDemo[] } = {};
let siguienteIdEntrega = 1;

const construirEntregas = (campanaId: number, idsContacto: number[], enviadoEn: string | null, idsFallidos: number[] = []): void => {
  entregas[campanaId] = idsContacto.map(id => {
    const c = contactos.find(x => x.id === id);
    const fallido = idsFallidos.includes(id);
    return {
      id: siguienteIdEntrega++,
      estado: enviadoEn === null ? 'pending' : fallido ? 'failed' : 'sent',
      reintentos: fallido ? 3 : 0,
      enviado_en: enviadoEn !== null && !fallido ? enviadoEn : null,
      mensaje_error: fallido ? 'Buzón no disponible (simulado)' : null,
      nombre: c?.nombre ?? '',
      apellidos: c?.apellidos ?? '',
      correo: c?.correo ?? ''
    };
  });
};

construirEntregas(1, [1, 2, 3], campanas[0].creado_en);
construirEntregas(2, [1, 2, 3, 4, 5], campanas[1].creado_en);
construirEntregas(3, [2, 5, 6, 7], campanas[2].creado_en, [7]);
construirEntregas(4, [1, 2, 3, 4, 5, 6], null);

let siguienteId = { contacto: 100, plantilla: 100, campana: 100, grupo: 100 };

const ok = (body: any) => of(new HttpResponse({ status: 200, body })).pipe(delay(350));
const fail = (status: number, message: string) =>
  throwError(() => new HttpErrorResponse({ status, error: { success: false, error: message } })).pipe(delay(350));

const contar = (campanaId: number, estado: string): number =>
  (entregas[campanaId] ?? []).filter(d => d.estado === estado).length;

/** Campaña tal y como la devuelve GET /history (con contadores por estado). */
const resumir = (c: any) => ({
  ...c,
  total_destinatarios: (entregas[c.id] ?? []).length,
  enviados: contar(c.id, 'sent'),
  fallidos: contar(c.id, 'failed'),
  pendientes: contar(c.id, 'pending'),
  omitidos: contar(c.id, 'skipped')
});

// ------- Variables de plantilla ({{nombre|valor por defecto}}) -------

const EJEMPLO = { nombre: 'Ana', apellidos: 'García', empresa: 'Empresa Ejemplo', cargo: 'Directora de Marketing', correo: 'ana.garcia@ejemplo.com' };
const CONOCIDAS = ['nombre', 'apellidos', 'nombre_completo', 'correo', 'empresa', 'cargo', 'enlace_baja'];
const PIE = '<hr><p style="font-size:12px;color:#888;text-align:center">Si no deseas recibir más correos, <a href="{{enlace_baja}}">date de baja aquí</a>.</p>';

const renderizar = (text: string, vars: Record<string, any>): string => {
  const data: Record<string, any> = { ...vars, nombre_completo: `${vars['nombre'] ?? ''} ${vars['apellidos'] ?? ''}`.trim() };

  return text.replace(/\{\{\s*([a-z_]+)\s*(?:\|([^}]*))?\}\}/gi, (whole, key: string, fallback?: string) => {
    const k = key.toLowerCase();
    if (!CONOCIDAS.includes(k)) return whole;
    return String(data[k] ?? '').trim() || (fallback ?? '').trim();
  });
};

const ideasAsunto = (tema: string): string[] => {
  const t = tema.trim();
  return [
    `${t}: lo que necesitas saber`,
    `Novedades sobre ${t} que te van a interesar`,
    `¿Has visto lo último en ${t}?`
  ];
};

const masDestacado = (counts: Map<string, number>): { nombre: string; total: number } | null => {
  let mejor: { nombre: string; total: number } | null = null;
  counts.forEach((total, nombre) => {
    if (!mejor || total > mejor.total) mejor = { nombre, total };
  });
  return mejor;
};

/** Simula el worker: una campaña en cola pasa a "completada" a los pocos segundos. */
const simularWorker = (campanaId: number): void => {
  setTimeout(() => {
    const campana = campanas.find(c => c.id === campanaId);
    if (!campana || campana.estado !== 'scheduled') return;

    campana.estado = 'processing';

    setTimeout(() => {
      const now = new Date().toISOString();
      (entregas[campanaId] ?? []).forEach(d => {
        if (d.estado === 'pending') { d.estado = 'sent'; d.enviado_en = now; }
      });
      campana.estado = 'completed';
      campana.actualizado_en = now;
    }, 2500);
  }, 1500);
};

const handle = (method: string, path: string, body: any) => {
  if (path === '/login' && method === 'POST') return ok({ success: true, token: demoToken() });
  if (path === '/register' && method === 'POST') return ok({ success: true });
  if (path === '/me' && method === 'GET') return ok({ success: true, data: { ...cuenta } });
  if (path === '/me' && method === 'PUT') {
    const correo = String(body?.correo ?? '').trim();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(correo)) return fail(400, 'El formato del correo no es válido');
    cuenta = { ...cuenta, correo };
    return ok({ success: true, data: { ...cuenta } });
  }
  if (path === '/me/contrasena' && method === 'PUT') {
    if (body?.contrasena_actual !== contrasenaCuenta) return fail(403, 'La contraseña actual no es correcta');
    if (String(body?.contrasena_nueva ?? '').length < 8) return fail(400, 'La nueva contraseña debe tener al menos 8 caracteres');
    if (body.contrasena_nueva === body.contrasena_actual) return fail(400, 'La nueva contraseña debe ser distinta de la actual');
    contrasenaCuenta = body.contrasena_nueva;
    return ok({ success: true, token: demoToken() });
  }
  if (path === '/logout' && method === 'POST') return ok({ success: true });

  // GRUPOS
  if (path === '/grupos' && method === 'GET') {
    const data = grupos.map(g => ({ ...g, total_miembros: contactos.filter(c => c.ids_grupo.includes(g.id)).length }));
    return ok({ success: true, data });
  }
  if (path === '/grupos' && method === 'POST') {
    const nombre = String(body?.nombre ?? '').trim();
    if (!nombre) return fail(400, 'El nombre del grupo es obligatorio');
    if (grupos.some(g => g.nombre.toLowerCase() === nombre.toLowerCase())) return fail(409, 'Ya existe un grupo con ese nombre');
    const grupo = { id: siguienteId.grupo++, nombre };
    grupos.push(grupo);
    return of(new HttpResponse({ status: 201, body: { success: true, id: grupo.id } })).pipe(delay(350));
  }
  let g = path.match(/^\/grupos\/(\d+)$/);
  if (g && method === 'PUT') {
    const grupo = grupos.find(x => x.id === +g![1]);
    const nombre = String(body?.nombre ?? '').trim();
    if (!grupo) return fail(404, 'Grupo no encontrado');
    if (!nombre) return fail(400, 'El nombre del grupo es obligatorio');
    if (grupos.some(x => x.id !== grupo.id && x.nombre.toLowerCase() === nombre.toLowerCase())) return fail(409, 'Ya existe un grupo con ese nombre');
    grupo.nombre = nombre;
    return ok({ success: true });
  }
  if (g && method === 'DELETE') {
    const i = grupos.findIndex(x => x.id === +g![1]);
    if (i < 0) return fail(404, 'Grupo no encontrado');
    const [removido] = grupos.splice(i, 1);
    contactos.forEach(c => { c.ids_grupo = c.ids_grupo.filter((id: number) => id !== removido.id); });
    return ok({ success: true });
  }

  // CONTACTOS
  if (path === '/contactos' && method === 'GET') return ok({ success: true, data: [...contactos].reverse() });
  if (path === '/contactos' && method === 'POST') {
    if (!body?.correo) return fail(400, 'El correo es obligatorio');
    if (contactos.some(c => c.correo.toLowerCase() === String(body.correo).toLowerCase())) {
      return fail(409, 'Ya existe un contacto con ese correo');
    }
    const contacto = { creado_en: new Date().toISOString(), ...body, id: siguienteId.contacto++, baja_en: null, ids_grupo: [] as number[] };
    contactos.push(contacto);
    return of(new HttpResponse({ status: 201, body: { success: true, id: contacto.id } })).pipe(delay(350));
  }
  if (path === '/contactos/import' && method === 'POST') {
    const filas: any[] = Array.isArray(body?.contactos) ? body.contactos : [];
    if (filas.length === 0) return fail(400, 'No hay contactos que importar');
    if (body?.grupo_id && !grupos.some(x => x.id === +body.grupo_id)) return fail(404, 'Grupo no encontrado');

    let creados = 0;
    let duplicados = 0;
    const invalidos: { fila: number; correo: string; motivo: string }[] = [];

    filas.forEach((fila, index) => {
      const correo = String(fila?.correo ?? '').trim();
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(correo)) {
        invalidos.push({ fila: index + 1, correo, motivo: correo ? 'Correo no válido' : 'Correo vacío' });
      } else if (contactos.some(c => c.correo.toLowerCase() === correo.toLowerCase())) {
        duplicados++;
      } else {
        contactos.push({
          id: siguienteId.contacto++, baja_en: null, creado_en: new Date().toISOString(),
          nombre: fila.nombre || null, apellidos: fila.apellidos || null, correo,
          telefono: fila.telefono || null, empresa: fila.empresa || null, cargo: fila.cargo || null,
          ids_grupo: body?.grupo_id ? [+body.grupo_id] : []
        });
        creados++;
      }
    });

    return of(new HttpResponse({ status: 201, body: { success: true, creados, duplicados, invalidos } })).pipe(delay(350));
  }
  let m = path.match(/^\/contactos\/(\d+)$/);
  if (m && method === 'PUT') {
    const c = contactos.find(x => x.id === +m![1]);
    if (!c) return fail(404, 'Contacto no encontrado');
    Object.assign(c, body, { id: c.id, baja_en: c.baja_en, ids_grupo: c.ids_grupo });
    return ok({ success: true });
  }
  m = path.match(/^\/contactos\/(\d+)\/grupos$/);
  if (m && method === 'PUT') {
    const c = contactos.find(x => x.id === +m![1]);
    if (!c) return fail(404, 'Contacto no encontrado');
    const ids: number[] = (body?.ids_grupo ?? []).map(Number);
    if (ids.some(id => !grupos.some(x => x.id === id))) return fail(404, 'Grupo no encontrado');
    c.ids_grupo = [...new Set(ids)];
    return ok({ success: true });
  }
  m = path.match(/^\/contactos\/(\d+)$/);
  if (m && method === 'DELETE') {
    const i = contactos.findIndex(x => x.id === +m![1]);
    if (i < 0) return fail(404, 'Contacto no encontrado');
    contactos.splice(i, 1);
    return ok({ success: true });
  }
  m = path.match(/^\/contactos\/(\d+)\/subscription$/);
  if (m && method === 'PUT') {
    const c = contactos.find(x => x.id === +m![1]);
    if (!c) return fail(404, 'Contacto no encontrado');
    c.baja_en = body?.subscribed ? null : new Date().toISOString();
    return ok({ success: true });
  }

  // PLANTILLAS
  if (path === '/plantillas' && method === 'GET') return ok({ success: true, data: [...plantillas].reverse() });
  if (path === '/plantillas' && method === 'POST') {
    if (!body?.nombre || !body?.asunto) return fail(400, 'Nombre, asunto y contenido son obligatorios');
    const now = new Date().toISOString();
    plantillas.push({ nombre: body.nombre, asunto: body.asunto, contenido_html: body.contenido_html ?? '', creado_en: now, actualizado_en: now, id: siguienteId.plantilla++ });
    return ok({ success: true });
  }
  m = path.match(/^\/plantillas\/(\d+)$/);
  if (m && method === 'PUT') {
    const t = plantillas.find(x => x.id === +m![1]);
    if (!t) return fail(404, 'Plantilla no encontrada');
    Object.assign(t, { nombre: body.nombre, asunto: body.asunto, contenido_html: body.contenido_html, actualizado_en: new Date().toISOString() });
    return ok({ success: true });
  }
  if (m && method === 'DELETE') {
    const i = plantillas.findIndex(x => x.id === +m![1]);
    if (i < 0) return fail(404, 'Plantilla no encontrada');
    plantillas.splice(i, 1);
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
    const contacto = contactos.find(c => c.id === +body?.contacto_id);
    const vars: Record<string, any> = { ...(contacto ?? EJEMPLO), enlace_baja: '#' };
    const html = String(body?.contenido_html ?? '');
    return ok({
      success: true,
      data: {
        asunto: renderizar(String(body?.asunto ?? ''), vars),
        cuerpo: renderizar(html.includes('{{enlace_baja') ? html : html + PIE, vars)
      }
    });
  }
  if (path === '/mail/test' && method === 'POST') return ok({ success: true, enviado_a: 'demo@example.com (simulado)' });

  // ENVÍOS
  if (path === '/send-mail' && method === 'POST') {
    if (!body?.destinatario || !body?.asunto) return fail(400, 'Destinatario, asunto y mensaje son obligatorios');
    const id = siguienteId.campana++;
    const now = new Date().toISOString();
    campanas.push({ id, nombre: null, tipo: 'single', asunto: body.asunto, plantilla_id: null, nombre_plantilla: null, estado: 'completed', programado_en: null, creado_en: now, actualizado_en: now });
    entregas[id] = [{ id: siguienteIdEntrega++, estado: 'sent', reintentos: 0, enviado_en: now, mensaje_error: null, nombre: '', apellidos: '', correo: body.destinatario }];
    return ok({ success: true });
  }
  if (path === '/send-massive' && method === 'POST') {
    const plantilla = plantillas.find(t => t.id === +body?.plantilla_id);
    if (!plantilla) return fail(404, 'Plantilla no encontrada');

    const solicitados: number[] = [...new Set<number>(body?.ids_contacto ?? [])];
    const validos = solicitados.filter(cid => contactos.some(c => c.id === cid && !c.baja_en));
    if (validos.length === 0) return fail(400, 'Ninguno de los contactos seleccionados puede recibir correos');

    const programado = body?.programado_en && new Date(body.programado_en).getTime() > Date.now();
    const id = siguienteId.campana++;
    const now = new Date().toISOString();
    campanas.push({
      id,
      nombre: body?.nombre ?? null,
      tipo: 'mass',
      asunto: plantilla.asunto,
      plantilla_id: plantilla.id,
      nombre_plantilla: plantilla.nombre,
      estado: 'scheduled',
      programado_en: programado ? new Date(body.programado_en).toISOString() : null,
      creado_en: now,
      actualizado_en: now
    });
    construirEntregas(id, validos, null);
    if (!programado) simularWorker(id);

    return of(new HttpResponse({
      status: 201,
      body: { success: true, campana_id: id, programado: !!programado, destinatarios: validos.length, excluidos: solicitados.length - validos.length }
    })).pipe(delay(350));
  }
  if (path === '/process-scheduled' && method === 'GET') return ok({ success: true, procesadas: 0 });
  if (path === '/history' && method === 'GET') {
    const ordenadas = [...campanas].sort((a, b) => +new Date(b.creado_en) - +new Date(a.creado_en));
    return ok({ success: true, data: ordenadas.map(resumir) });
  }
  m = path.match(/^\/history\/(\d+)\/deliveries$/);
  if (m && method === 'GET') return ok({ success: true, data: entregas[+m[1]] ?? [] });
  m = path.match(/^\/history\/(\d+)\/cancel$/);
  if (m && method === 'POST') {
    const c = campanas.find(x => x.id === +m![1]);
    if (!c || c.estado !== 'scheduled') return fail(409, 'Solo se pueden cancelar campañas programadas que no hayan empezado');
    c.estado = 'cancelled';
    (entregas[c.id] ?? []).forEach(d => { if (d.estado === 'pending') { d.estado = 'skipped'; d.mensaje_error = 'Campaña cancelada'; } });
    return ok({ success: true });
  }
  m = path.match(/^\/history\/(\d+)\/retry-failed$/);
  if (m && method === 'POST') {
    const c = campanas.find(x => x.id === +m![1]);
    const fallidas = (entregas[+m![1]] ?? []).filter(d => d.estado === 'failed');
    if (!c) return fail(404, 'Campaña no encontrada');
    if (c.estado !== 'completed' || fallidas.length === 0) return fail(409, 'La campaña no tiene entregas fallidas');
    fallidas.forEach(d => { d.estado = 'pending'; d.mensaje_error = null; d.reintentos = 0; });
    c.estado = 'scheduled';
    simularWorker(c.id);
    return ok({ success: true, reencolados: fallidas.length });
  }

  // IA (simulada)
  if (path === '/ai/suggest-asunto' && method === 'POST') {
    if (!body?.topic) return fail(400, 'Indica de qué trata el correo');
    return ok({ success: true, subjects: ideasAsunto(body.topic) });
  }

  // PANEL
  if (path === '/dashboard/stats' && method === 'GET') {
    const todas = Object.values(entregas).flat();
    return ok({
      success: true,
      data: {
        total_campanas: campanas.filter(c => c.tipo === 'mass').length,
        total_contactos: contactos.length,
        total_plantillas: plantillas.length,
        total_enviados: todas.filter(d => d.estado === 'sent').length,
        total_fallidos: todas.filter(d => d.estado === 'failed').length
      }
    });
  }
  if (path === '/dashboard/activity' && method === 'GET') {
    const dias: { fecha: string; enviados: number }[] = [];
    for (let i = 13; i >= 0; i--) {
      const d = new Date();
      d.setUTCDate(d.getUTCDate() - i);
      const fecha = d.toISOString().slice(0, 10);
      const enviados = Object.values(entregas).flat().filter(x => x.estado === 'sent' && x.enviado_en?.slice(0, 10) === fecha).length;
      dias.push({ fecha, enviados });
    }
    return ok({ success: true, data: dias });
  }
  if (path === '/dashboard/summary' && method === 'GET') {
    const porPlantilla = new Map<string, number>();
    campanas.filter(c => c.tipo === 'mass').forEach(c => {
      if (c.nombre_plantilla) porPlantilla.set(c.nombre_plantilla, (porPlantilla.get(c.nombre_plantilla) ?? 0) + 1);
    });
    const porContacto = new Map<string, number>();
    Object.entries(entregas).forEach(([cid, list]) => {
      if (campanas.find(c => c.id === +cid)?.tipo !== 'mass') return;
      list.filter(d => d.estado === 'sent').forEach(d => {
        const nombre = `${d.nombre ?? ''} ${d.apellidos ?? ''}`.trim() || d.correo;
        porContacto.set(nombre, (porContacto.get(nombre) ?? 0) + 1);
      });
    });
    return ok({ success: true, data: { plantilla_top: masDestacado(porPlantilla), contacto_top: masDestacado(porContacto) } });
  }

  return fail(404, 'Ruta no disponible en la demo.');
};

export const demoInterceptor: HttpInterceptorFn = (req) => {
  const marker = 'index.php';
  const at = req.url.indexOf(marker);
  const path = (at >= 0 ? req.url.slice(at + marker.length) : req.url).replace(/\/$/, '');
  return handle(req.method, path, req.body);
};
