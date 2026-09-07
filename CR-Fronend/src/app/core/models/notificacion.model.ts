/**
 * Un aviso guardado para el trabajador.
 *
 * Hoy solo se crea uno: "ya está tu boleta del mes, fírmala". A diferencia
 * de lo que había antes —contar los documentos sin firmar cada vez que se
 * abría la pantalla— el aviso queda grabado con su fecha, así que se puede
 * ver el historial aunque la boleta ya esté firmada.
 */
export interface Notificacion {
  id: string;
  tipo: string;
  titulo: string;
  mensaje: string;
  /** La boleta a la que lleva. Puede faltar si el documento se borró. */
  documento_id: string | null;
  leida: boolean;
  leida_at: string | null;
  created_at: string;
}
