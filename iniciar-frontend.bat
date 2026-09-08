@echo off
title CATA Recibos - Frontend (Angular)
echo ========================================================
echo   Iniciando Frontend - Colegio Tupac Amaru (Angular)
echo ========================================================
cd /d "%~dp0CR-Fronend"

if not exist "node_modules\" (
    echo [INFO] Instalando librerias de Angular...
    call npm install --legacy-peer-deps
)

echo.
echo [INFO] Levantando servidor Angular en http://localhost:4200 ...
echo [INFO] Para detener el servidor presiona Ctrl + C
echo.
call npm start
pause
