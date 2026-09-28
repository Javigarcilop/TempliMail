import { Injectable, signal } from '@angular/core';

export type ToastKind = 'success' | 'error' | 'info';

export interface Toast {
  id: number;
  kind: ToastKind;
  text: string;
}

/** Avisos flotantes globales (se cierran solos). */
@Injectable({ providedIn: 'root' })
export class ToastService {

  readonly toasts = signal<Toast[]>([]);

  private nextId = 1;

  success(text: string): void { this.show('success', text); }
  error(text: string): void { this.show('error', text, 6000); }
  info(text: string): void { this.show('info', text); }

  dismiss(id: number): void {
    this.toasts.update(list => list.filter(t => t.id !== id));
  }

  private show(kind: ToastKind, text: string, ttl = 4000): void {
    const id = this.nextId++;

    this.toasts.update(list => [...list, { id, kind, text }]);
    setTimeout(() => this.dismiss(id), ttl);
  }
}
