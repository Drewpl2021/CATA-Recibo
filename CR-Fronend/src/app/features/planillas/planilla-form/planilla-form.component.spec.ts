import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PlanillaFormComponent } from './planilla-form.component';

describe('PlanillaFormComponent', () => {
  let component: PlanillaFormComponent;
  let fixture: ComponentFixture<PlanillaFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PlanillaFormComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PlanillaFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
