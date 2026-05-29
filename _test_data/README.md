# 🧪 Datos de Prueba — CATA-Recibo

Esta carpeta contiene ÚNICAMENTE scripts para insertar datos de prueba durante el desarrollo.

> ⚠️ NO forma parte del código del proyecto.
> Tu compañero del Backend se encargará de crear los datos reales.
> Esta carpeta puede eliminarse antes de ir a producción.

## Scripts disponibles

| Script | Descripción |
|---|---|
| `seed_planilla.php` | Crea 12 registros de planilla (uno por mes) para el usuario de prueba |
| `crear_usuario_prueba.php` | Crea el empleado + usuario de prueba (test@colegio.com) |
| `limpiar_datos.php` | Elimina todos los datos de prueba de la BD |

## Cómo ejecutar

Desde la carpeta `CR-Fronend`, ejecutar:

```powershell
C:\xampp\php\php.exe ..\_test_data\seed_planilla.php
C:\xampp\php\php.exe ..\_test_data\limpiar_datos.php
```
