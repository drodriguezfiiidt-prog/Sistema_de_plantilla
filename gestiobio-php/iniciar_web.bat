@echo off
setlocal enabledelayedexpansion
title GestioBioV3 PHP - servidor local
cd /d "%~dp0"

set "PORT=8001"
set "PHP_EXE="

if exist "%~dp0php\php.exe" set "PHP_EXE=%~dp0php\php.exe"
if not defined PHP_EXE if exist "C:\Users\DR\php85\php.exe" set "PHP_EXE=C:\Users\DR\php85\php.exe"
if not defined PHP_EXE if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE (
    for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
)

if not defined PHP_EXE (
    echo.
    echo No se encontro PHP en el sistema.
    echo Instale PHP 8.1 o superior ^(https://windows.php.net/download/^),
    echo o copie la carpeta php\ con php.exe dentro de este proyecto.
    echo.
    pause
    exit /b 1
)

echo Usando PHP: "!PHP_EXE!"
"!PHP_EXE!" -r "exit(PHP_VERSION_ID >= 80100 ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo Se requiere PHP 8.1 o superior.
    pause
    exit /b 1
)

"!PHP_EXE!" -r "exit(extension_loaded('pdo_sqlite') && extension_loaded('zip') && extension_loaded('mbstring') ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo Faltan extensiones de PHP: pdo_sqlite, zip y mbstring.
    echo Revise el php.ini ^(vease README.md^).
    pause
    exit /b 1
)

if not exist "%~dp0storage\gestiobio.sqlite" (
    echo Inicializando base de datos y catalogo...
    "!PHP_EXE!" "%~dp0bin\init-db.php"
    echo.
)

echo.
echo GestioBioV3 PHP disponible en http://127.0.0.1:%PORT%/
echo Usuario de demostracion: admin / GestioBio2026*
echo Para detener el servidor ejecute detener_web.bat
echo.

start "" powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 2; Start-Process 'http://127.0.0.1:%PORT%/'"

"!PHP_EXE!" -S 127.0.0.1:%PORT% -t "%~dp0public" "%~dp0router.php"

endlocal
