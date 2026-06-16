import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Area {
  id: string;
  nombre: string;
}

@Injectable({ providedIn: 'root' })
export class AreaService {
  private apiUrl = environment.apiUrl + '/areas';

  constructor(private http: HttpClient) {}

  getAreas(): Observable<{ success: boolean; data: Area[] }> {
    return this.http.get<{ success: boolean; data: Area[] }>(this.apiUrl);
  }
}
