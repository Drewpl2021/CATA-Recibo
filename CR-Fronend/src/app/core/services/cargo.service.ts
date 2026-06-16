import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Cargo {
  id: string;
  nombre: string;
}

@Injectable({ providedIn: 'root' })
export class CargoService {
  private apiUrl = environment.apiUrl + '/cargos';

  constructor(private http: HttpClient) {}

  getCargos(): Observable<{ success: boolean; data: Cargo[] }> {
    return this.http.get<{ success: boolean; data: Cargo[] }>(this.apiUrl);
  }
}
