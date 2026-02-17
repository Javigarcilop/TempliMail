import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';
import { EditorModule } from '@tinymce/tinymce-angular';

@Component({
  selector: 'app-templates',
  standalone: true,
  imports: [CommonModule, FormsModule, EditorModule],
  templateUrl: './templates.component.html',
  styleUrls: ['./templates.component.css']
})
export class TemplatesComponent implements OnInit {

  templates: any[] = [];

  templateForm = {
    id: null as number | null,
    name: '',
    subject: '',
    content_html: ''
  };

  editing = false;
  message = '';
  messageVisible = false;

  selectedFile: any = null;

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.loadTemplates();
  }

  loadTemplates(): void {
    this.api.getTemplates().subscribe((data: any[]) => {
      this.templates = data;
    });
  }

  saveTemplate(): void {

    if (this.editing && this.templateForm.id !== null) {

      this.api.updateTemplate(this.templateForm.id, this.templateForm)
        .subscribe(() => {
          this.showMessage('✅ Template updated successfully');
          this.loadTemplates();
          this.resetForm();
        });

    } else {

      this.api.addTemplate(this.templateForm)
        .subscribe(() => {
          this.showMessage('✅ Template saved successfully');
          this.loadTemplates();
          this.resetForm();
        });
    }
  }

  uploadFile(): void {

    if (this.selectedFile?.target?.files?.length > 0) {

      const file = this.selectedFile.target.files[0];
      const formData = new FormData();
      formData.append('file', file);

      this.api.uploadTemplateFile(formData).subscribe({
        next: (response: any) => {
          if (response.success) {
            this.templateForm.content_html = response.html;
            this.showMessage('✅ File loaded into editor');
          }
        },
        error: () => this.showMessage('❌ Upload error')
      });
    }
  }

  editTemplate(template: any): void {
    this.templateForm = { ...template };
    this.editing = true;
  }

  deleteTemplate(id: number): void {
    if (confirm('¿Eliminar plantilla?')) {
      this.api.deleteTemplate(id).subscribe(() => {
        this.showMessage('🗑️ Template deleted');
        this.loadTemplates();
      });
    }
  }

  resetForm(): void {
    this.templateForm = {
      id: null,
      name: '',
      subject: '',
      content_html: ''
    };
    this.editing = false;
    this.selectedFile = null;
  }

  showMessage(text: string): void {
    this.message = text;
    this.messageVisible = true;
    setTimeout(() => this.messageVisible = false, 3000);
  }
}
