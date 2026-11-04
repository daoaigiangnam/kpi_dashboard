@echo off
setlocal
cd /d "%~dp0"
chcp 65001 >nul
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0AuditTool.ps1"
if errorlevel 1 (
    echo.
    echo Audit that bai. Vui long chup man hinh loi gui cho IT.
    pause
)
endlocal
