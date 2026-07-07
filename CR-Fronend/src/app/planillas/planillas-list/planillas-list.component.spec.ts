import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PlanillasListComponent } from './planillas-list.component';

describe('PlanillasListComponent', () => {
  let component: PlanillasListComponent;
  let fixture: ComponentFixture<PlanillasListComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PlanillasListComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PlanillasListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
