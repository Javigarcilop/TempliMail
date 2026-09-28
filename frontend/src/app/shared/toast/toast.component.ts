import { Component, inject } from '@angular/core';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-toast',
  standalone: true,
  template: `
    <div class="toast-stack" aria-live="polite">
      @for (toast of toasts.toasts(); track toast.id) {
        <div class="toast" [class]="'toast toast-' + toast.kind" role="status">
          <span>{{ toast.text }}</span>
          <button type="button" aria-label="Cerrar" (click)="toasts.dismiss(toast.id)">×</button>
        </div>
      }
    </div>
  `,
  styles: [`
    .toast-stack {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 2000;
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 380px;
    }
    .toast {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
      border: 1px solid transparent;
      animation: toast-in 0.2s ease-out;
    }
    .toast-success { background: #e6f4ea; color: #276749; border-color: #c6eccd; }
    .toast-error   { background: #fcebea; color: #a94442; border-color: #f5c6cb; }
    .toast-info    { background: #e8f1fb; color: #1f4e79; border-color: #c5dcf3; }
    .toast button {
      background: none;
      border: none;
      color: inherit;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
      padding: 0;
      opacity: 0.6;
    }
    .toast button:hover { opacity: 1; }
    @keyframes toast-in {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: none; }
    }
  `]
})
export class ToastComponent {
  readonly toasts = inject(ToastService);
}
