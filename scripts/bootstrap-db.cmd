@echo off
setlocal

REM Calls the PowerShell bootstrap script (recommended on Windows)
powershell -ExecutionPolicy Bypass -File "%~dp0bootstrap-db.ps1"
if errorlevel 1 exit /b 1

endlocal