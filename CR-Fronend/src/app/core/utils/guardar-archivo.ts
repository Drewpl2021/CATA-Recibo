/**
 * Guarda en la carpeta de descargas un archivo que mandó el servidor.
 *
 * Lo usan la lista de empleados y los Excel modelo de las importaciones. El
 * navegador no guarda un Blob por sí solo: se le da un enlace temporal, se
 * hace clic y se suelta el enlace cuando la descarga ya empezó (soltarlo en
 * el acto la corta en algunos navegadores).
 */
export function guardarArchivo(blob: Blob, nombre: string): void {
  const url = URL.createObjectURL(blob);
  const enlace = document.createElement('a');
  enlace.href = url;
  enlace.download = nombre;
  enlace.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
