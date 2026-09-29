import { DEMO_MODE } from './demo-mode';

// En la demo publicada, TinyMCE no puede cargarse desde tiny.cloud porque el dominio
// de GitHub Pages no está en la lista de dominios aprobados de la clave de la app real.
// Se sirve una copia local (GPL) en vez de depender de esa clave.
export const tinymceScriptSrc = DEMO_MODE
  ? new URL('tinymce/tinymce.min.js', document.baseURI).toString()
  : undefined;

export const tinymceLicenseKey = DEMO_MODE ? 'gpl' : undefined;
