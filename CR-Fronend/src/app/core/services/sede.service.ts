import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Sede {
  id: string;
  nombre: string;
  direccion: string | null;
}

export interface SedeResponse {
  success: boolean;
  data: Sede[];
}

@Injectable({ providedIn: 'root' })
export class SedeService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getSedes(): Observable<SedeResponse> {
    return this.http.get<SedeResponse>(`${this.apiUrl}/sedes`);
  }
}
