@echo off
set "TASK_NAME=LaravelScheduler"
schtasks /Query /TN "%TASK_NAME%" >nul 2>&1
if %ERRORLEVEL%==0 (
  schtasks /Delete /TN "%TASK_NAME%" /F
  echo [OK] Tarea "%TASK_NAME%" eliminada.
) else (
  echo [INFO] La tarea "%TASK_NAME%" no existe.
)
pause
