import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService, ToastMessage } from '../../../core/services/toast.service';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="toast-container toast-enter" *ngIf="toast" [ngClass]="toast.type">
      <div class="toast-icon">
        <!-- Success -->
        <svg *ngIf="toast.type === 'success'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <!-- Error -->
        <svg *ngIf="toast.type === 'error'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <!-- Info -->
        <svg *ngIf="toast.type === 'info'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <!-- Warning -->
        <svg *ngIf="toast.type === 'warning'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div class="toast-content">
        <h4 class="toast-title" *ngIf="toast.title">{{ toast.title }}</h4>
        <p class="toast-message" *ngIf="toast.message" [innerHTML]="toast.message"></p>
      </div>
      <button class="toast-close" (click)="close()">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
  `,
  styles: [`
    .toast-container {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 9999;
      display: flex;
      align-items: flex-start;
      gap: 16px;
      padding: 16px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 2px 10px rgba(0,0,0,0.05);
      min-width: 320px;
      max-width: 450px;
      border-left: 5px solid transparent;
      transform-origin: bottom right;
    }

    .toast-container.success { border-left-color: #2ecc71; .toast-icon { color: #2ecc71; } }
    .toast-container.error { border-left-color: #e74c3c; .toast-icon { color: #e74c3c; } }
    .toast-container.warning { border-left-color: #f39c12; .toast-icon { color: #f39c12; } }
    .toast-container.info { border-left-color: #3498db; .toast-icon { color: #3498db; } }

    .toast-icon {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 2px;
    }

    .toast-content {
      flex: 1;
    }

    .toast-title {
      margin: 0 0 4px 0;
      font-size: 1.05rem;
      font-weight: 600;
      color: #1a1f36;
    }

    .toast-message {
      margin: 0;
      font-size: 0.95rem;
      color: #4a5568;
      line-height: 1.5;
    }

    .toast-close {
      background: transparent;
      border: none;
      color: #a0aec0;
      cursor: pointer;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }

    .toast-close:hover {
      color: #4a5568;
    }

    .toast-enter {
      animation: slideIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    @keyframes slideIn {
      from { transform: translateY(100%); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
  `],
  // Removed Angular animations, using pure CSS instead
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
