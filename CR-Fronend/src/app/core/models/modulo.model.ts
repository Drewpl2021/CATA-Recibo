import { Rol } from './configuracion.model';

/** Módulos del sidebar dinámico (GET /mis-modulos, según el rol del usuario). */

export interface ModuloHijo {
  id: string;
  nombre: string;
  ruta: string;
  icono: string;
  orden: number;
}

export interface ModuloPadre {
  id: string;
  nombre: string;
  icono: string;
  orden: number;
  modulos: ModuloHijo[];
}


/* ══════════════════════════════════════════════════════════════════
   ADMINISTRACIÓN DEL MENÚ (solo Admin)
   ══════════════════════════════════════════════════════════════════
   Cómo encaja todo:

     modulo_padre  ─┬─ modulos ─── rol_modulo ─── roles
                    │
       "el grupo    │   "el ítem       "quién lo ve
        del menú"   │    del menú"      en su menú"

   1. Se crea un MÓDULO PADRE: es el grupo que agrupa ítems en la barra
      lateral ("Configuración", "Boletas y Finanzas"…).
   2. Se crea un MÓDULO colgando de ese padre, con la RUTA de Angular a
      la que lleva ("/areas") y su ícono.
   3. Se le asignan ROLES. Esa relación es la que decide a quién le
      aparece el ítem en su barra lateral (GET /mis-modulos lo filtra
      por el rol del usuario).

   OJO: asignar un rol a un módulo solo controla lo que se VE en el menú.
   El permiso real sobre los endpoints lo sigue imponiendo el middleware
   `rol:` de routes/api.php en el backend.
   ══════════════════════════════════════════════════════════════════ */

/** Un módulo padre tal como lo devuelve GET /modulos-padre (administración). */
export interface ModuloPadreAdmin {
  id: string;
  nombre: string;
  icono?: string | null;
  orden: number;
  estado_registro?: string;
}

export interface ModuloPadrePayload {
  nombre: string;
  icono?: string | null;
  orden?: number;
}

/** Un módulo tal como lo devuelve GET /modulos (viene con padre y roles). */
export interface ModuloAdmin {
  id: string;
  modulo_padre_id: string;
  nombre: string;
  ruta?: string | null;
  icono?: string | null;
  orden: number;
  estado_registro?: string;
  modulo_padre?: ModuloPadreAdmin | null;
  roles?: Rol[];
}

export interface ModuloPayload {
  modulo_padre_id: string;
  nombre: string;
  ruta?: string | null;
  icono?: string | null;
  orden?: number;
}

/** Cuerpo de POST /modulos/{id}/roles — reemplaza la lista completa. */
export interface AsignarRolesPayload {
  roles: string[];
}
