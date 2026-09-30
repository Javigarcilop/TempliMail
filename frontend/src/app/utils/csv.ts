/**
 * Utilidades CSV sin dependencias.
 * Acepta ',', ';' o tabulador como separador (Excel en español usa ';'),
 * campos entre comillas con saltos de línea y comillas escapadas ("").
 */

export function detectDelimiter(text: string): string {
  const firstLine = text.split(/\r?\n/, 1)[0] ?? '';
  const candidates = [';', ',', '\t'];
  let best = ',';
  let bestCount = 0;

  for (const candidate of candidates) {
    const count = firstLine.split(candidate).length - 1;

    if (count > bestCount) {
      best = candidate;
      bestCount = count;
    }
  }

  return best;
}

export function parseCsv(input: string): string[][] {
  const text = input.replace(/^﻿/, '');
  const delimiter = detectDelimiter(text);
  const rows: string[][] = [];

  let row: string[] = [];
  let field = '';
  let inQuotes = false;

  for (let i = 0; i < text.length; i++) {
    const char = text[i];

    if (inQuotes) {
      if (char === '"') {
        if (text[i + 1] === '"') {
          field += '"';
          i++;
        } else {
          inQuotes = false;
        }
      } else {
        field += char;
      }
      continue;
    }

    if (char === '"') {
      inQuotes = true;
    } else if (char === delimiter) {
      row.push(field);
      field = '';
    } else if (char === '\n' || char === '\r') {
      if (char === '\r' && text[i + 1] === '\n') {
        i++;
      }
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
    } else {
      field += char;
    }
  }

  // Última línea sin salto final
  if (field !== '' || row.length > 0) {
    row.push(field);
    rows.push(row);
  }

  return rows.filter(r => r.some(cell => cell.trim() !== ''));
}

// Excel (y similares) interpretan una celda que empieza por =, +, - o @ como una
// fórmula. Anteponer una comilla simple neutraliza el riesgo sin cambiar lo que se ve.
const FORMULA_PREFIX = /^[=+\-@]/;

export function toCsv(rows: (string | number | null | undefined)[][], delimiter = ';'): string {
  const escape = (value: string | number | null | undefined): string => {
    let text = value === null || value === undefined ? '' : String(value);

    if (FORMULA_PREFIX.test(text)) {
      text = `'${text}`;
    }

    return /["\r\n]/.test(text) || text.includes(delimiter)
      ? `"${text.replace(/"/g, '""')}"`
      : text;
  };

  return rows.map(row => row.map(escape).join(delimiter)).join('\r\n');
}

// -------------------------------------------------------------------
// Contactos: cabeceras reconocidas (español e inglés)
// -------------------------------------------------------------------

export type ContactField = 'nombre' | 'apellidos' | 'correo' | 'telefono' | 'empresa' | 'cargo';

const HEADER_ALIASES: Record<ContactField, string[]> = {
  correo: ['email', 'e-mail', 'correo', 'correo electronico', 'mail'],
  nombre: ['first_name', 'firstname', 'nombre', 'name'],
  apellidos: ['last_name', 'lastname', 'apellidos', 'apellido', 'surname'],
  telefono: ['phone', 'telefono', 'tel', 'movil', 'mobile'],
  empresa: ['company', 'empresa', 'organizacion'],
  cargo: ['position', 'cargo', 'puesto', 'job', 'title']
};

const normalize = (value: string): string =>
  value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

export interface ParsedContacts {
  contacts: Record<ContactField, string>[];
  /** Columnas de la cabecera que no se reconocieron (se ignoran) */
  ignoredColumns: string[];
}

/**
 * Convierte un CSV con cabecera en contactos. Devuelve null si no hay columna de correo.
 */
export function parseContactsCsv(text: string): ParsedContacts | null {
  const rows = parseCsv(text);

  if (rows.length < 2) {
    return null;
  }

  const header = rows[0].map(normalize);
  const columns: Partial<Record<ContactField, number>> = {};
  const used = new Set<number>();

  (Object.keys(HEADER_ALIASES) as ContactField[]).forEach(field => {
    const index = header.findIndex(h => HEADER_ALIASES[field].includes(h));

    if (index >= 0) {
      columns[field] = index;
      used.add(index);
    }
  });

  if (columns.correo === undefined) {
    return null;
  }

  const contacts = rows.slice(1).map(row => {
    const contact = {} as Record<ContactField, string>;

    (Object.keys(HEADER_ALIASES) as ContactField[]).forEach(field => {
      const index = columns[field];
      contact[field] = index !== undefined ? (row[index] ?? '').trim() : '';
    });

    return contact;
  });

  return {
    contacts,
    ignoredColumns: rows[0].filter((_, index) => !used.has(index) && rows[0][index].trim() !== '')
  };
}
