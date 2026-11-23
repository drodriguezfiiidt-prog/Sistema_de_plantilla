@echo off
setlocal
title GestioBioV3 - Detener servidor

powershell -NoProfile -ExecutionPolicy Bypass -Command "$c = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue; if (-not $c) { Write-Host 'No hay ningun servidor escuchando en el puerto 8000.' } else { $c | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue; Write-Host ('Servidor detenido (PID ' + $_.OwningProcess + ').') } }"

echo.
pause
