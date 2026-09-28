
# 📬 TempliMail

> **Demo en vivo:** https://javigarcilop.github.io/TempliMail/ (datos simulados en el navegador, sin backend; no envía correos reales).

**TempliMail es una aplicación web profesional de email marketing.** Permite crear, gestionar y enviar correos electrónicos personalizados mediante plantillas visuales enriquecidas. 

TempliMail combina un frontend moderno desarrollado en Angular 19 con un backend robusto en PHP puro, conectados a una base de datos MySQL estructurada, eficiente y escalable. No es solo una solución funcional en su estado actual, sino que está pensada para evolucionar y escalar, incorporando en futuras versiones funcionalidades como estadísticas de apertura, gestión multicuenta, control de roles, exportación a PDF, e integración con plataformas externas.

---

## 🛠️ Tecnologías Utilizadas

- **Frontend**: Angular 19, Bootstrap, TinyMCE  
- **Backend**: PHP (puro, sin frameworks), PHPMailer, PHPWord, PDFParser  
- **Base de datos**: MySQL  
- **Entorno local**: XAMPP  

---

## ✅ Funcionalidades Principales

- Login de administrador  
- Gestión avanzada de contactos (CRUD + búsqueda)  
- Editor visual de plantillas (TinyMCE)  
- Carga de plantillas desde archivos .docx y .pdf  
- Envío de correos individual y masivo (SMTP real con PHPMailer)  
- Envío programado automático por fecha y hora  
- Historial de envíos  
- Interfaz moderna y responsiva  

---

## 📁 Estructura del Proyecto

```
TempliMail/
├── frontend/           --> Proyecto Angular 19
├── backend/            --> Backend PHP (API REST modular)
├── templimail_db.sql   --> Base de datos MySQL exportada
└── README.md           --> Este archivo
```

---

## ⚙️ Requisitos del Sistema
 
- XAMPP instalado (PHP 8.x + MySQL + Apache)  
- Node.js y Angular CLI  

---

## 🚀 Guía de Instalación y Despliegue (Local)

### 1. Clonar o descomprimir el proyecto en `C:/xampp/htdocs/TempliMail`

### 2. Importar la base de datos:

- Abrir phpMyAdmin  
- Crear base de datos: `templimail_db`  
- Importar `templimail_db.sql`  

### 3. Backend (PHP)

- Ubicar en `C:/xampp/htdocs/TempliMail/backend/`  
- Acceder vía navegador a: `http://localhost/TempliMail/backend/api/`  

### 4. Frontend (Angular)

```bash
cd frontend
npm install
ng serve --open
```

Acceso vía navegador: `http://localhost:4200`

---

## 📝 Información del Proyecto

- **Nombre**: TempliMail  
- **Autor**: Francisco Javier García López   
- **Fecha de inicio**: Abril 2025  

---

## 🔧 Notas adicionales

- El envío programado se ejecuta automáticamente desde frontend.  
- No es necesario cron ni tareas del sistema.  
- Preparado para futuras ampliaciones: estadísticas, multicuenta, roles.  

---


---

## 🌐 Demo pública

La demo es el mismo frontend Angular compilado con `ng build --configuration demo`. Sustituye las llamadas a la API PHP por un backend simulado en memoria (`frontend/src/app/demo/demo.interceptor.ts`), así que se puede alojar como web estática en GitHub Pages. Se despliega sola con cada push a `main` (`.github/workflows/demo.yml`).

Para probarla en local:

```bash
cd frontend
npx ng serve --configuration demo
```
