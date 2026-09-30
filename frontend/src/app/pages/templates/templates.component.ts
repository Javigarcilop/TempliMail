import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { EditorModule, TINYMCE_SCRIPT_SRC } from '@tinymce/tinymce-angular';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { MailPreviewComponent } from '../../shared/mail-preview/mail-preview.component';
import { VistaPreviaCorreo, Plantilla, PlantillaInput, VARIABLES_PLANTILLA } from '../../models/api.models';
import { environment } from '../../../environments/environment';
import { tinymceScriptSrc, tinymceLicenseKey } from '../../demo/tinymce-self-host';

const PLANTILLA_VACIA: PlantillaInput = {
  id: null,
  nombre: '',
  asunto: '',
  contenido_html: ''
};

@Component({
  selector: 'app-templates',
  standalone: true,
  imports: [CommonModule, FormsModule, EditorModule, MailPreviewComponent],
  providers: [{ provide: TINYMCE_SCRIPT_SRC, useValue: tinymceScriptSrc }],
  templateUrl: './templates.component.html',
  styleUrls: ['./templates.component.css']
})
export class TemplatesComponent implements OnInit {

  readonly tinymceApiKey = environment.tinymceApiKey;
  readonly tinymceLicenseKey = tinymceLicenseKey;
  // Las llaves se exponen desde aquí: en la plantilla, "{{" se interpretaría como interpolación
  readonly variables = VARIABLES_PLANTILLA.map(v => ({ ...v, tag: `{{${v.key}}}` }));
  readonly exampleDefault = "{{nombre|amigo}}";
  readonly exampleUnsubscribe = "{{enlace_baja}}";

  templates: Plantilla[] = [];
  templateForm: PlantillaInput = { ...PLANTILLA_VACIA };
  editing = false;

  selectedFile: Event | null = null;

  preview: VistaPreviaCorreo | null = null;
  busy = false;

  private editor: { insertContent(html: string): void } | null = null;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.loadTemplates();
  }

  onEditorInit(event: { editor: { insertContent(html: string): void } }): void {
    this.editor = event.editor;
  }

  loadTemplates(): void {
    this.api.getTemplates().subscribe({
      next: response => this.templates = response.data ?? [],
      error: () => this.toast.error('No se pudieron cargar las plantillas.')
    });
  }

  saveTemplate(): void {
    if (!this.templateForm.nombre.trim() || !this.templateForm.asunto.trim()) {
      this.toast.error('El nombre y el asunto son obligatorios.');
      return;
    }

    const wasEditing = this.editing && this.templateForm.id != null;

    const request = wasEditing
      ? this.api.updateTemplate(this.templateForm.id as number, this.templateForm)
      : this.api.addTemplate(this.templateForm);

    request.subscribe({
      next: () => {
        this.toast.success(wasEditing ? 'Plantilla actualizada.' : 'Plantilla guardada.');
        this.loadTemplates();
        this.resetForm();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo guardar la plantilla.')
    });
  }

  uploadFile(): void {
    const input = this.selectedFile?.target as HTMLInputElement | undefined;
    const file = input?.files?.[0];

    if (!file) {
      this.toast.info('Selecciona primero un archivo .docx o .pdf.');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);

    this.api.uploadTemplateFile(formData).subscribe({
      next: response => {
        if (response.success) {
          this.templateForm.contenido_html = response.html;
          this.toast.success('Archivo cargado en el editor.');
        }
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'Error al subir el archivo.')
    });
  }

  editTemplate(template: Plantilla): void {
    this.templateForm = {
      id: template.id,
      nombre: template.nombre,
      asunto: template.asunto,
      contenido_html: template.contenido_html
    };
    this.editing = true;
    window.scrollTo?.({ top: 0 });
  }

  duplicateTemplate(template: Plantilla): void {
    this.api.addTemplate({
      nombre: `${template.nombre} (copia)`.slice(0, 255),
      asunto: template.asunto,
      contenido_html: template.contenido_html
    }).subscribe({
      next: () => {
        this.toast.success('Plantilla duplicada.');
        this.loadTemplates();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo duplicar la plantilla.')
    });
  }

  deleteTemplate(template: Plantilla): void {
    if (!confirm(`¿Eliminar la plantilla "${template.nombre}"?`)) {
      return;
    }

    this.api.deleteTemplate(template.id).subscribe({
      next: () => {
        this.toast.success('Plantilla eliminada.');
        this.loadTemplates();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo eliminar la plantilla.')
    });
  }

  resetForm(): void {
    this.templateForm = { ...PLANTILLA_VACIA };
    this.editing = false;
    this.selectedFile = null;
  }

  // ---------------------------------------------------------------
  // Variables, vista previa y prueba
  // ---------------------------------------------------------------

  insertVariable(key: string): void {
    const tag = `{{${key}}}`;

    if (this.editor) {
      this.editor.insertContent(tag);
    } else {
      this.templateForm.contenido_html += tag;
    }
  }

  showPreview(): void {
    this.api.previewMail(this.mailPayload()).subscribe({
      next: response => this.preview = response.data,
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo generar la vista previa.')
    });
  }

  sendTest(): void {
    this.busy = true;

    this.api.sendTestMail(this.mailPayload()).subscribe({
      next: response => {
        this.busy = false;
        this.toast.success(`Correo de prueba enviado a ${response.enviado_a}.`);
      },
      error: (err: HttpErrorResponse) => {
        this.busy = false;
        this.toast.error(err.error?.error ?? 'No se pudo enviar la prueba.');
      }
    });
  }

  private mailPayload() {
    return {
      asunto: this.templateForm.asunto,
      contenido_html: this.templateForm.contenido_html
    };
  }
}
