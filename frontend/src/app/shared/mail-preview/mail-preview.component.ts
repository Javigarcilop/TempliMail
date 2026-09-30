import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

/**
 * Modal con la vista previa de un correo ya renderizado.
 * El HTML se muestra en un iframe con `sandbox` vacío: sin scripts, sin
 * formularios y sin navegación, así que un contenido malicioso no puede ejecutarse.
 */
@Component({
  selector: 'app-mail-preview',
  standalone: true,
  template: `
    <div class="backdrop" (click)="close.emit()">
      <div class="modal" role="dialog" aria-modal="true" (click)="$event.stopPropagation()">
        <header>
          <div>
            <small>Asunto</small>
            <strong>{{ asunto }}</strong>
          </div>
          <button type="button" class="close" aria-label="Cerrar" (click)="close.emit()">×</button>
        </header>

        <iframe sandbox="" title="Vista previa del correo" [srcdoc]="safeHtml"></iframe>

        <footer>
          <small>Vista previa con datos de ejemplo o del primer contacto seleccionado.</small>
          <button type="button" class="ok" (click)="close.emit()">Cerrar</button>
        </footer>
      </div>
    </div>
  `,
  styles: [`
    .backdrop {
      position: fixed;
      inset: 0;
      background: rgba(19, 47, 76, 0.55);
      z-index: 1500;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .modal {
      background: #fff;
      border-radius: 14px;
      width: min(760px, 100%);
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
    header, footer {
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    header { border-bottom: 1px solid #e5e9ef; }
    header small { display: block; color: #7a8594; font-size: 12px; }
    header strong { color: #1f2c44; }
    footer { border-top: 1px solid #e5e9ef; }
    footer small { color: #7a8594; }
    iframe { border: 0; width: 100%; height: 480px; background: #fff; }
    .close {
      background: none; border: none; font-size: 28px; line-height: 1;
      cursor: pointer; color: #7a8594; margin: 0; padding: 0; box-shadow: none;
    }
    .ok {
      background: #132f4c; color: #fff; border: none; border-radius: 8px;
      padding: 8px 18px; cursor: pointer; font-weight: 600; margin: 0;
    }
  `]
})
export class MailPreviewComponent {
  @Input({ required: true }) asunto = '';
  @Output() close = new EventEmitter<void>();

  safeHtml: SafeHtml = '';

  private sanitizer = inject(DomSanitizer);

  @Input({ required: true }) set cuerpo(value: string) {
    // Es seguro marcarlo como fiable porque el iframe usa sandbox="" (sin scripts)
    this.safeHtml = this.sanitizer.bypassSecurityTrustHtml(value);
  }
}
