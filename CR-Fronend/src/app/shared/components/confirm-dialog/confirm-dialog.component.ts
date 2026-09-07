import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subscription } from 'rxjs';
import { ConfirmService, ConfirmRequest } from '../../../core/services';

@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './confirm-dialog.component.html',
})
export class ConfirmDialogComponent implements OnInit, OnDestroy {
  request: ConfirmRequest | null = null;
  private subscription: Subscription | null = null;

  constructor(private confirmService: ConfirmService) {}

  ngOnInit(): void {
    this.subscription = this.confirmService.request$.subscribe((req) => {
      this.request = req;
    });
  }

  ngOnDestroy(): void {
    this.subscription?.unsubscribe();
  }

  aceptar(): void {
    this.request?.resolve(true);
  }

  cancelar(): void {
    this.request?.resolve(false);
  }
}
