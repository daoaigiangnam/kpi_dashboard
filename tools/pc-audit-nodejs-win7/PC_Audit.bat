@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title MSTAR PC Audit Tool - Windows 7 / 10 / 11 - Node.js

echo ==============================================
echo   MSTAR PC AUDIT TOOL - WINDOWS 7 / 10 / 11
echo ==============================================
echo.

rem If Node.js already exists, do not reinstall it.
set "NODE_EXE="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if defined NODE_EXE (
  for /f "delims=" %%V in ('node --version 2^>nul') do set "NODE_VERSION=%%V"
  echo Found Node.js %NODE_VERSION% at:
  echo %NODE_EXE%
  echo Skipping Node.js installation.
  goto RUN_AUDIT
)

echo Node.js not found. Detecting Windows version and architecture...

rem Detect architecture: x64 or x86.
set "ARCH=x86"
if /i "%PROCESSOR_ARCHITEW6432%"=="AMD64" set "ARCH=x64"
if /i "%PROCESSOR_ARCHITECTURE%"=="AMD64" set "ARCH=x64"
if /i "%PROCESSOR_ARCHITECTURE%"=="ARM64" set "ARCH=arm64"

rem Windows 7 reports version 6.1. Install Node.js 12.22.12.
rem Windows 10/11 report version 10.0. Install Node.js 22 LTS.
set "NODE_VERSION_REQUIRED=v22.16.0"
set "NODE_URL_BASE=https://nodejs.org/dist/v22.16.0/"
set "NODE_MSI=node-v22.16.0-%ARCH%.msi"

for /f "tokens=2 delims=[]" %%V in ('ver') do set "WIN_VER=%%V"
echo Windows version: %WIN_VER%
echo Architecture: %ARCH%

ver | findstr /c:"6.1." >nul
if not errorlevel 1 (
  set "NODE_VERSION_REQUIRED=v12.22.12"
  set "NODE_URL_BASE=https://nodejs.org/dist/v12.22.12/"
  set "NODE_MSI=node-v12.22.12-%ARCH%.msi"
)

set "NODE_URL=%NODE_URL_BASE%%NODE_MSI%"
set "TEMP_MSI=%TEMP%\%NODE_MSI%"

net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo [ERROR] Node.js installation needs Administrator privileges.
  echo Right-click PC_Audit.bat and choose Run as administrator.
  pause
  exit /b 10
)

echo.
echo Downloading official Node.js installer:
echo %NODE_URL%
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $wc = New-Object Net.WebClient; $wc.DownloadFile('%NODE_URL%','%TEMP_MSI%')" >nul 2>&1
if not exist "%TEMP_MSI%" (
  certutil -urlcache -split -f "%NODE_URL%" "%TEMP_MSI%" >nul 2>&1
)
if not exist "%TEMP_MSI%" (
  echo.
  echo [ERROR] Automatic download failed. Open the official Node.js download URL:
  echo %NODE_URL%
  start "" "%NODE_URL%"
  echo Install the MSI manually, then run this BAT again.
  pause
  exit /b 11
)
for %%F in ("%TEMP_MSI%") do if %%~zF LSS 100000 (
  del /f /q "%TEMP_MSI%" >nul 2>&1
  echo [ERROR] MSI download is incomplete. Please download manually:
  echo %NODE_URL%
  start "" "%NODE_URL%"
  pause
  exit /b 12
)

echo Installing Node.js %NODE_VERSION_REQUIRED%...
msiexec.exe /i "%TEMP_MSI%" /passive /norestart
if errorlevel 1 (
  echo [ERROR] Node.js installer failed. Run this MSI manually:
  echo %TEMP_MSI%
  pause
  exit /b 13
)
del /f /q "%TEMP_MSI%" >nul 2>&1

rem Refresh PATH for the current process.
set "PATH=%ProgramFiles%\nodejs;%ProgramFiles(x86)%\nodejs;%PATH%"
set "NODE_EXE="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if not defined NODE_EXE (
  echo Node.js installed, but node.exe is not available in this CMD.
  echo Close this window and run PC_Audit.bat again.
  pause
  exit /b 14
)
for /f "delims=" %%V in ('node --version 2^>nul') do set "NODE_VERSION=%%V"
echo Installed Node.js: %NODE_VERSION%

:RUN_AUDIT
echo.
node "%~dp0PC_Audit.js"
set "AUDIT_EXIT=%ERRORLEVEL%"
if not "%AUDIT_EXIT%"=="0" (
  echo.
  echo PC Audit failed. Exit code: %AUDIT_EXIT%
  echo If JSON was created, it is in the output folder.
  pause
)
endlocal & exit /b %AUDIT_EXIT%
