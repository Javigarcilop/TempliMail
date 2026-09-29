import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { EditorModule, TINYMCE_SCRIPT_SRC } from '@tinymce/tinymce-angular';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { MailPreviewComponent } from '../../shared/mail-preview/mail-preview.component';
import { MailPreview, Template, TemplateInput, TEMPLATE_VARIABLES } from '../../models/api.models';
import { environment } from '../../../environments/environment';
import { tinymceScriptSrc, tinymceLicenseKey } from '../../demo/tinymce-self-host';

const EMPTY_TEMPLATE: TemplateInput = {
  id: null,
  name: '',
  subject: '',
  content_html: ''
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
  readonly variables = TEMPLATE_VARIABLES.map(v => ({ ...v, tag: `{{${v.key}}}` }));
  readonly exampleDefault = "{{first_name|amigo}}";
  readonly exampleUnsubscribe = "{{unsubscribe_url}}";

  templates: Template[] = [];
  templateForm: TemplateInput = { ...EMPTY_TEMPLATE };
  editing = false;

  selectedFile: Event | null = null;

  preview: MailPreview | null = null;
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
    if (!this.templateForm.name.trim() || !this.templateForm.subject.trim()) {
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
          this.templateForm.content_html = response.html;
          this.toast.success('Archivo cargado en el editor.');
        }
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'Error al subir el archivo.')
    });
  }

  editTemplate(template: Template): void {
    this.templateForm = {
      id: template.id,
      name: template.name,
      subject: template.subject,
      content_html: template.content_html
    };
    this.editing = true;
    window.scrollTo?.({ top: 0 });
  }

  duplicateTemplate(template: Template): void {
    this.api.addTemplate({
      name: `${template.name} (copia)`.slice(0, 255),
      subject: template.subject,
      content_html: template.content_html
    }).subscribe({
      next: () => {
        this.toast.success('Plantilla duplicada.');
        this.loadTemplates();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo duplicar la plantilla.')
    });
  }

  deleteTemplate(template: Template): void {
    if (!confirm(`¿Eliminar la plantilla "${template.name}"?`)) {
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
    this.templateForm = { ...EMPTY_TEMPLATE };
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
      this.templateForm.content_html += tag;
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
        this.toast.success(`Correo de prueba enviado a ${response.sent_to}.`);
      },
      error: (err: HttpErrorResponse) => {
        this.busy = false;
        this.toast.error(err.error?.error ?? 'No se pudo enviar la prueba.');
      }
    });
  }

  private mailPayload() {
    return {
      subject: this.templateForm.subject,
      content_html: this.templateForm.content_html
    };
  }
}
