import { detectDelimiter, parseContactsCsv, parseCsv, toCsv } from './csv';

describe('csv', () => {

  it('detecta y separa por coma, punto y coma y tabulador', () => {
    expect(parseCsv('a,b\n1,2')).toEqual([['a', 'b'], ['1', '2']]);
    expect(parseCsv('a;b\r\n1;2\r\n')).toEqual([['a', 'b'], ['1', '2']]);
    expect(parseCsv('a\tb\n1\t2')).toEqual([['a', 'b'], ['1', '2']]);
    expect(detectDelimiter('nombre;email;empresa')).toBe(';');
  });

  it('respeta comillas, comillas escapadas y saltos de línea dentro del campo', () => {
    expect(parseCsv('a,b\n"x, y",2')).toEqual([['a', 'b'], ['x, y', '2']]);
    expect(parseCsv('a\n"di ""hola"""')).toEqual([['a'], ['di "hola"']]);
    expect(parseCsv('a,b\n"l1\nl2",2')).toEqual([['a', 'b'], ['l1\nl2', '2']]);
  });

  it('ignora el BOM y las líneas vacías', () => {
    expect(parseCsv('﻿a,b\n\n1,2\n,\n')).toEqual([['a', 'b'], ['1', '2']]);
  });

  it('toCsv escapa y se puede volver a leer', () => {
    expect(toCsv([['a;b', 'c"d', 'e']])).toBe('"a;b";"c""d";e');
    expect(parseCsv(toCsv([['x;y', 'l1\nl2', '"q"']]))).toEqual([['x;y', 'l1\nl2', '"q"']]);
  });

  it('reconoce cabeceras de contactos en español (con tildes) e inglés', () => {
    const result = parseContactsCsv(
      'Nombre;Apellidos;Correo electrónico;Teléfono;Empresa;Cargo;Notas\nAna;López;ana@x.com;600;ACME;CEO;vip'
    );

    expect(result?.contacts[0]).toEqual({
      email: 'ana@x.com', first_name: 'Ana', last_name: 'López', phone: '600', company: 'ACME', position: 'CEO'
    });
    expect(result?.ignoredColumns).toEqual(['Notas']);
    expect(parseContactsCsv('email,first_name\na@x.com,Ana')?.contacts[0].first_name).toBe('Ana');
  });

  it('devuelve null si no hay columna de email o solo hay cabecera', () => {
    expect(parseContactsCsv('nombre,empresa\nAna,X')).toBeNull();
    expect(parseContactsCsv('email')).toBeNull();
  });
});
