@echo off
setlocal
cd /d "%~dp0"
title MSTAR PC Audit Tool - Node.js Windows 7
where node >nul 2>&1
if errorlevel 1 (
  echo Node.js chua duoc cai dat hoac chua co trong PATH.
  echo Vui long cai Node.js 12.22.12 dung kien truc x86/x64 truoc khi chay.
  echo Xem README-Windows7.txt de biet cach cai dat.
  pause
  exit /b 2
)
node --version
node "%~dp0PC_Audit.js"
set AUDIT_EXIT=%ERRORLEVEL%
if not "%AUDIT_EXIT%"=="0" (
  echo.
  echo PC Audit that bai. Ma loi: %AUDIT_EXIT%
  echo Neu JSON da tao, file nam trong thu muc output.
  pause
)
endlocal & exit /b %AUDIT_EXIT%
