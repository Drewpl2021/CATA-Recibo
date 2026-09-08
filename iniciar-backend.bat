@echo off
title CATA Recibos - Backend (Laravel)
echo ========================================================
echo   Iniciando Backend - Colegio Tupac Amaru (Laravel)
echo ========================================================
cd /d "%~dp0CR-Backend"

echo.
echo [INFO] Levantando servidor Laravel en http://127.0.0.1:8000 ...
echo [INFO] Para detener el servidor presiona Ctrl + C
echo.
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
pause
