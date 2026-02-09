import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';
import { EditorModule } from '@tinymce/tinymce-angular';

@Component({
  selector: 'app-templates',
  standalone: true,
  imports: [CommonModule, FormsModule, HttpClientModule, EditorModule],
  templateUrl: './templates.component.html',
  styleUrls: ['./templates.component.css']
})
export class TemplatesComponent implements OnInit {
  plantillas: any[] = [];

  nueva = {
    id: null,
    nombre: '',
    asunto: '',
    contenido_html: ''
  };

  editando = false;
  mensaje: string = '';
  mensajeVisible = false;

  archivoSeleccionado: any = null;

  constructor(private api: ApiService) { }

  ngOnInit() {
    this.cargarPlantillas();
  }

  cargarPlantillas() {
    this.api.getPlantillas().subscribe(data => {
      this.plantillas = data;
    });
  }

  agregarPlantilla() {
    if (this.editando && this.nueva.id !== null) {
      this.api.updatePlantilla(this.nueva.id, this.nueva).subscribe(() => {
        this.mostrarMensaje('✅ Plantilla actualizada correctamente');
        this.cargarPlantillas();
        this.resetFormulario();
      }, error => {
        this.mostrarMensaje('❌ Error al actualizar la plantilla');
        console.error(error);
      });
    } else {
      this.api.addPlantilla(this.nueva).subscribe(() => {
        this.mostrarMensaje('✅ Plantilla guardada con éxito');
        this.cargarPlantillas();
        this.resetFormulario();
      }, error => {
        this.mostrarMensaje('❌ Error al guardar la plantilla');
        console.error(error);
      });
    }
  }

  subirArchivo() {
    if (this.archivoSeleccionado && this.archivoSeleccionado.target.files.length > 0) {
      const file = this.archivoSeleccionado.target.files[0];
      const formData = new FormData();
      formData.append('file', file);

      this.api.uploadTemplateFile(formData).subscribe({
        next: (response: any) => {
          if (response.success) {
            this.nueva.contenido_html = response.html;
            this.mostrarMensaje('✅ Archivo cargado en el editor');
          } else {
            this.mostrarMensaje('❌ Error al procesar el archivo');
          }
        },
        error: () => {
          this.mostrarMensaje('❌ Error al subir el archivo');
        }
      });
    } else {
      alert('Por favor selecciona un archivo primero.');
    }
  }

  editarPlantilla(plantilla: any) {
    this.nueva = { ...plantilla };
    this.editando = true;
  }

  eliminarPlantilla(id: number) {
    if (confirm('¿Estás segura de que deseas eliminar esta plantilla?')) {
      this.api.deletePlantilla(id).subscribe({
        next: (res: any) => {
          if (res.success) {
            this.mostrarMensaje('🗑️ Plantilla eliminada con éxito');
            this.cargarPlantillas();
          } else {
            this.mostrarMensaje(res.error || '❌ No se pudo eliminar la plantilla');
          }
        },
        error: (err) => {
          const msg = err.error?.error || '❌ Error inesperado al eliminar';
          this.mostrarMensaje(msg);
          console.error('Error al eliminar:', err);
        }
      });
    }
  }


  resetFormulario() {
    this.nueva = {
      id: null,
      nombre: '',
      asunto: '',
      contenido_html: ''
    };
    this.editando = false;
    this.archivoSeleccionado = null;
  }

  mostrarMensaje(texto: string) {
    this.mensaje = texto;
    this.mensajeVisible = true;
    setTimeout(() => {
      this.mensajeVisible = false;
    }, 3000);
  }
}
