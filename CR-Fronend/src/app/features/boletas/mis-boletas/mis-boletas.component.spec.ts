import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MisBoletasComponent } from './mis-boletas.component';

describe('MisBoletasComponent', () => {
  let component: MisBoletasComponent;
  let fixture: ComponentFixture<MisBoletasComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MisBoletasComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MisBoletasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
