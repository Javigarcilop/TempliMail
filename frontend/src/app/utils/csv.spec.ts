import { detectarDelimitador, analizarContactosCsv, analizarCsv, convertirACsv } from './csv';

describe('csv', () => {

  it('detecta y separa por coma, punto y coma y tabulador', () => {
    expect(analizarCsv('a,b\n1,2')).toEqual([['a', 'b'], ['1', '2']]);
    expect(analizarCsv('a;b\r\n1;2\r\n')).toEqual([['a', 'b'], ['1', '2']]);
    expect(analizarCsv('a\tb\n1\t2')).toEqual([['a', 'b'], ['1', '2']]);
    expect(detectarDelimitador('nombre;email;empresa')).toBe(';');
  });

  it('respeta comillas, comillas escapadas y saltos de línea dentro del campo', () => {
    expect(analizarCsv('a,b\n"x, y",2')).toEqual([['a', 'b'], ['x, y', '2']]);
    expect(analizarCsv('a\n"di ""hola"""')).toEqual([['a'], ['di "hola"']]);
    expect(analizarCsv('a,b\n"l1\nl2",2')).toEqual([['a', 'b'], ['l1\nl2', '2']]);
  });

  it('ignora el BOM y las líneas vacías', () => {
    expect(analizarCsv('﻿a,b\n\n1,2\n,\n')).toEqual([['a', 'b'], ['1', '2']]);
  });

  it('toCsv escapa y se puede volver a leer', () => {
    expect(convertirACsv([['a;b', 'c"d', 'e']])).toBe('"a;b";"c""d";e');
    expect(analizarCsv(convertirACsv([['x;y', 'l1\nl2', '"q"']]))).toEqual([['x;y', 'l1\nl2', '"q"']]);
  });

  it('toCsv antepone comilla simple a celdas que empiezan por =, +, - o @ (inyección de fórmulas)', () => {
    expect(convertirACsv([['=cmd', '+1', '-1', '@x', 'normal']])).toBe("'=cmd;'+1;'-1;'@x;normal");
  });

  it('reconoce cabeceras de contactos en español (con tildes) e inglés', () => {
    const result = analizarContactosCsv(
      'Nombre;Apellidos;Correo electrónico;Teléfono;Empresa;Cargo;Notas\nAna;López;ana@x.com;600;ACME;CEO;vip'
    );

    expect(result?.contacts[0]).toEqual({
      correo: 'ana@x.com', nombre: 'Ana', apellidos: 'López', telefono: '600', empresa: 'ACME', cargo: 'CEO'
    });
    expect(result?.ignoredColumns).toEqual(['Notas']);
    expect(analizarContactosCsv('email,first_name\na@x.com,Ana')?.contacts[0].nombre).toBe('Ana');
  });

  it('devuelve null si no hay columna de correo o solo hay cabecera', () => {
    expect(analizarContactosCsv('nombre,empresa\nAna,X')).toBeNull();
    expect(analizarContactosCsv('email')).toBeNull();
  });
});
