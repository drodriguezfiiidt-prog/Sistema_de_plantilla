@echo off
setlocal
title GestioBioV3 - Servidor de desarrollo
cd /d "%~dp0"

echo ============================================================
echo   GestioBioV3 - Asistente de Soporte Tecnico
echo   Biopago C.A. - Soporte Postventa
echo ============================================================
echo.

where py >nul 2>&1
if errorlevel 1 goto :sin_python

echo [1/4] Verificando dependencias (requirements.txt)...
py -m pip install -r requirements.txt --quiet --disable-pip-version-check
if errorlevel 1 goto :error

echo [2/4] Aplicando migraciones...
py manage.py migrate --noinput
if errorlevel 1 goto :error

echo [3/4] Cargando catalogo inicial (no duplica datos)...
py manage.py loaddata initial_catalog
if errorlevel 1 goto :error

echo [4/4] Iniciando servidor en http://127.0.0.1:8000/
echo.
echo   Generador ....... http://127.0.0.1:8000/
echo   Historial ....... http://127.0.0.1:8000/historial/
echo   Gestion ......... http://127.0.0.1:8000/gestion/   (requiere usuario staff)
echo   Administracion .. http://127.0.0.1:8000/admin/
echo.
echo   Para detener el servidor presione Ctrl + C en esta ventana.
echo.
start "" http://127.0.0.1:8000/
py manage.py runserver 127.0.0.1:8000
goto :fin

:sin_python
echo [ERROR] No se encontro el lanzador "py" de Python.
echo Instale Python 3.10 o superior y marque "Add python.exe to PATH".
goto :error

:error
echo.
echo [ERROR] No se pudo iniciar la aplicacion. Revise el mensaje anterior.
echo.

:fin
pause
