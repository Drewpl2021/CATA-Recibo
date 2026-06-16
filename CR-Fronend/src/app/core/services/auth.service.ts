import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface LoginResponse {
  success: boolean;
  data: {
    user: {
      id: number;
      name: string;
      email: string;
      rol: any;
      empleado_id: string;
    };
    token: string;
    rol: string;
  };
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient, private router: Router) {}

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, { email, password }).pipe(
      tap((res) => {
        if (res.success) {
          // PARCHE: Extraer el rol si viene como objeto desde el backend
          if (res.data.user && typeof res.data.user.rol === 'object' && res.data.user.rol !== null) {
            res.data.user.rol = res.data.user.rol.nombre || 'empleado';
          }
          if (typeof res.data.rol === 'object' && res.data.rol !== null) {
            res.data.rol = (res.data.rol as any).nombre || 'empleado';
          }

          localStorage.setItem('auth_token', res.data.token);
          localStorage.setItem('auth_user', JSON.stringify(res.data.user));
        }
      })
    );
  }

  logout(): void {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    this.router.navigate(['/login']);
  }

  getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  getUser(): LoginResponse['data']['user'] | null {
    const user = localStorage.getItem('auth_user');
    return user ? JSON.parse(user) : null;
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  getEmpleadoId(): string | null {
    return this.getUser()?.empleado_id ?? null;
  }
}
