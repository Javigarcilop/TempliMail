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

  exito(text: string): void { this.mostrar('success', text); }
  fallo(text: string): void { this.mostrar('error', text, 6000); }
  aviso(text: string): void { this.mostrar('info', text); }

  descartar(id: number): void {
    this.toasts.update(list => list.filter(t => t.id !== id));
  }

  private mostrar(kind: ToastKind, text: string, ttl = 4000): void {
    const id = this.nextId++;

    this.toasts.update(list => [...list, { id, kind, text }]);
    setTimeout(() => this.descartar(id), ttl);
  }
}
