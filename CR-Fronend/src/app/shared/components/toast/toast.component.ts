import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService, ToastMessage } from '../../../core/services';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './toast.component.html',
})
export class ToastComponent implements OnInit, OnDestroy {
  toast: ToastMessage | null = null;
  private subscription: Subscription | null = null;

  constructor(private toastService: ToastService) {}

  ngOnInit() {
    this.subscription = this.toastService.toastState$.subscribe(toast => {
      this.toast = toast;
    });
  }

  ngOnDestroy() {
    if (this.subscription) {
      this.subscription.unsubscribe();
    }
  }

  close() {
    this.toastService.clear();
  }
}
