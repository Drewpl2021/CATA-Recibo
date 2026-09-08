@echo off
title CATA Recibos - Iniciar Sistema Completo
echo ========================================================
echo   Iniciando Sistema Completo - Colegio Tupac Amaru
echo ========================================================
echo.
echo [1/2] Levantando Backend (Laravel) en http://127.0.0.1:8000 ...
start "CATA Recibos - Backend" cmd /k "%~dp0iniciar-backend.bat"

timeout /t 3 /nobreak >nul

echo [2/2] Levantando Frontend (Angular) en http://localhost:4200 ...
start "CATA Recibos - Frontend" cmd /k "%~dp0iniciar-frontend.bat"

echo.
echo Sistema iniciado con exito en dos ventanas.
echo Backend:  http://127.0.0.1:8000
echo Frontend: http://localhost:4200
echo.
timeout /t 5
