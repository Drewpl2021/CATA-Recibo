import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';

export interface LoginResponse {
  success: boolean;
  data: {
    user: {
      id: number;
      name: string;
      email: string;
      rol: string;
      empleado_id: string;
    };
    token: string;
    rol: string;
  };
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly apiUrl = 'http://cr-backend.test/api';

  constructor(private http: HttpClient, private router: Router) {}

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, { email, password }).pipe(
      tap((res) => {
        if (res.success) {
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
