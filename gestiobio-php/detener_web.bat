@echo off
setlocal
title GestioBioV3 PHP - detener servidor

set "PORT=8001"
set "FOUND="

for /f "tokens=1,2,3,4,5" %%A in ('netstat -ano ^| findstr /c:":%PORT%" ^| findstr /i "LISTENING"') do (
    echo Deteniendo proceso %%E que escucha en el puerto %PORT%...
    taskkill /PID %%E /F >nul 2>&1
    set "FOUND=1"
)

if not defined FOUND (
    echo No hay ningun servidor escuchando en el puerto %PORT%.
) else (
    echo Servidor PHP detenido.
)

timeout /t 2 >nul
endlocal
