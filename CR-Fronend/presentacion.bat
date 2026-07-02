@echo off
color 0B
echo =======================================================
echo        CATA RECIBO - PREPARACION DE PRESENTACION
echo =======================================================
echo.

echo Buscando la carpeta del Backend...
cd ..\CR-Backend

echo [1/4] Limpiando basura y caches antiguas...
call php artisan cache:clear
call php artisan config:clear
call php artisan route:clear
call php artisan view:clear
echo [OK] Caches limpias.
echo.

echo [2/4] Verificando enlaces de archivos (Storage)...
call php artisan storage:link >nul 2>&1
echo [OK] Storage link verificado.
echo.

echo [3/4] BASE DE DATOS
echo ATENCION: Si hubo cambios recientes en la base de datos por parte 
echo del backend, es MUY recomendable resetearla para evitar errores.
echo.
set /p resetDB="¿Deseas borrar toda la DB y cargar datos de prueba limpios? (S/N): "
if /I "%resetDB%"=="S" (
    echo.
    echo Reseteando Base de Datos. Por favor espera...
    call php artisan migrate:fresh --seed
    echo [OK] Base de datos lista y con datos de prueba.
) else (
    echo [OK] Se conservan los datos actuales de la Base de Datos.
)
echo.

echo =======================================================
echo [4/4] LEVANTANDO SERVIDOR BACKEND
echo =======================================================
echo El Backend esta corriendo. ¡NO CIERRES ESTA VENTANA!
echo URL: http://127.0.0.1:8000
echo =======================================================
echo.
php artisan serve
