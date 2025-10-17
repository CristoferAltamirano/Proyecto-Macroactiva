@echo off
setlocal ENABLEDELAYEDEXPANSION

REM ================== CONFIG ==================
REM Ruta a php.exe
set "PHP_EXE=C:\xampp\php\php.exe"
REM Carpeta del proyecto Laravel (donde está artisan)
set "PROJECT_DIR=C:\Users\crist\Desktop\macroactiva"
REM Nombre de la tarea
set "TASK_NAME=LaravelScheduler"
REM ============================================

if not exist "%PROJECT_DIR%\artisan" (
  echo [ERROR] No se encontro artisan en "%PROJECT_DIR%".
  echo Edita PROJECT_DIR en este bat.
  pause
  exit /b 1
)

if not exist "%PHP_EXE%" (
  echo [ERROR] No se encontro PHP en "%PHP_EXE%".
  echo Edita PHP_EXE en este bat.
  pause
  exit /b 1
)

REM Asegurar carpeta de logs
if not exist "%PROJECT_DIR%\storage\logs" mkdir "%PROJECT_DIR%\storage\logs" >nul 2>&1

REM Si existe, borrarla para recrear limpia
schtasks /Query /TN "%TASK_NAME%" >nul 2>&1
if %ERRORLEVEL%==0 (
  echo [INFO] Tarea existente encontrada. Eliminando...
  schtasks /Delete /TN "%TASK_NAME%" /F >nul
)

echo [INFO] Creando tarea "%TASK_NAME%" cada 1 minuto...
schtasks /Create ^
  /TN "%TASK_NAME%" ^
  /SC MINUTE /MO 1 ^
  /TR "cmd /c cd /d \"%PROJECT_DIR%\" && \"%PHP_EXE%\" artisan schedule:run >> \"storage\logs\schedule_runner.log\" 2>&1" ^
  /RL HIGHEST ^
  /F

if %ERRORLEVEL% NEQ 0 (
  echo [ERROR] No se pudo crear la tarea. Ejecuta este .bat como Administrador.
  pause
  exit /b 1
)

echo [OK] Tarea creada.
echo [INFO] Lanzandola una vez para probar...
schtasks /Run /TN "%TASK_NAME%"
echo [INFO] Revisa: %PROJECT_DIR%\storage\logs\schedule_runner.log
echo [DONE]
pause
endlocal
