import { Injectable } from '@angular/core';
import { Subject, Observable } from 'rxjs';

export interface ToastMessage {
  type: 'success' | 'error' | 'info' | 'warning';
  title: string;
  message: string;
  duration?: number;
}

@Injectable({
  providedIn: 'root'
})
export class ToastService {
  private toastSubject = new Subject<ToastMessage | null>();
  public toastState$: Observable<ToastMessage | null> = this.toastSubject.asObservable();

  show(type: 'success' | 'error' | 'info' | 'warning', title: string, message: string, duration: number = 3500) {
    this.toastSubject.next({ type, title, message, duration });
    
    if (duration > 0) {
      setTimeout(() => {
        this.clear();
      }, duration);
    }
  }

  success(title: string, message: string = '') {
    this.show('success', title, message);
  }

  error(title: string, message: string = '') {
    this.show('error', title, message);
  }

  info(title: string, message: string = '') {
    this.show('info', title, message);
  }

  warning(title: string, message: string = '') {
    this.show('warning', title, message);
  }

  clear() {
    this.toastSubject.next(null);
  }
}
