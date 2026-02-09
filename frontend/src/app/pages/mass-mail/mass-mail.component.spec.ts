import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MassMailComponent } from './mass-mail.component';

describe('MassMailComponent', () => {
  let component: MassMailComponent;
  let fixture: ComponentFixture<MassMailComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MassMailComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MassMailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
