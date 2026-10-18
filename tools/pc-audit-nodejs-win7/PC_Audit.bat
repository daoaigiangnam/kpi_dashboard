@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title MSTAR PC Audit - Windows 7 / 10 / 11

echo ==============================================
echo   MSTAR PC AUDIT TOOL - WINDOWS 7 / 10 / 11
echo ==============================================
echo.

rem Detect Windows version before validating Node.js.
set "NODE_VERSION_REQUIRED=v22.16.0"
set "NODE_URL_BASE=https://nodejs.org/dist/v22.16.0/"
ver | findstr /c:"6.1." >nul
if not errorlevel 1 (
  set "NODE_VERSION_REQUIRED=v12.22.12"
  set "NODE_URL_BASE=https://nodejs.org/dist/v12.22.12/"
)

rem Detect architecture.
set "ARCH=x86"
if /i "%PROCESSOR_ARCHITEW6432%"=="AMD64" set "ARCH=x64"
if /i "%PROCESSOR_ARCHITECTURE%"=="AMD64" set "ARCH=x64"
if /i "%PROCESSOR_ARCHITECTURE%"=="ARM64" set "ARCH=arm64"

set "NODE_MSI=node-%NODE_VERSION_REQUIRED:~1%-%ARCH%.msi"
set "NODE_URL=%NODE_URL_BASE%%NODE_MSI%"

echo Required Node.js: %NODE_VERSION_REQUIRED%
echo Architecture: %ARCH%
echo.

rem Detect installed Node.js. Skip install only when exact required version matches.
set "NODE_EXE="
set "NODE_VERSION="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if defined NODE_EXE (
  for /f "delims=" %%V in ('node --version 2^>nul') do set "NODE_VERSION=%%V"
  echo Found Node.js: %NODE_VERSION%
  echo Path: %NODE_EXE%
  if "%NODE_VERSION%"=="%NODE_VERSION_REQUIRED%" (
    echo Version matches. Skipping Node.js installation.
    goto RUN_AUDIT
  )
  echo.
  echo [WARNING] Installed Node.js version does not match the required version.
  echo Required: %NODE_VERSION_REQUIRED%
  echo Installed: %NODE_VERSION%
  echo The tool will try to install the required version.
)

rem Install/repair the required exact version.
net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo [ERROR] Node.js installation needs Administrator privileges.
  echo Right-click PC_Audit.bat and choose Run as administrator.
  pause
  exit /b 10
)

set "TEMP_MSI=%TEMP%\%NODE_MSI%"
if exist "%TEMP_MSI%" del /f /q "%TEMP_MSI%" >nul 2>&1
echo.
echo Downloading official installer:
echo %NODE_URL%
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $wc = New-Object Net.WebClient; $wc.DownloadFile('%NODE_URL%','%TEMP_MSI%')" >nul 2>&1
if not exist "%TEMP_MSI%" certutil -urlcache -split -f "%NODE_URL%" "%TEMP_MSI%" >nul 2>&1

if not exist "%TEMP_MSI%" (
  echo.
  echo [ERROR] Download failed. Open the official installer URL:
  echo %NODE_URL%
  start "" "%NODE_URL%"
  echo Install the MSI, then run this BAT again.
  pause
  exit /b 11
)
for %%F in ("%TEMP_MSI%") do if %%~zF LSS 100000 (
  del /f /q "%TEMP_MSI%" >nul 2>&1
  echo [ERROR] MSI download is incomplete:
  echo %NODE_URL%
  start "" "%NODE_URL%"
  pause
  exit /b 12
)

echo Installing Node.js %NODE_VERSION_REQUIRED%...
msiexec.exe /i "%TEMP_MSI%" /passive /norestart
if errorlevel 1 (
  echo.
  echo [ERROR] Installer failed. If another Node.js version is installed,
  echo uninstall it from Control Panel, then run this BAT again.
  echo MSI: %TEMP_MSI%
  pause
  exit /b 13
)
del /f /q "%TEMP_MSI%" >nul 2>&1

set "PATH=%ProgramFiles%\nodejs;%ProgramFiles(x86)%\nodejs;%PATH%"
set "NODE_EXE="
set "NODE_VERSION="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if defined NODE_EXE for /f "delims=" %%V in ('node --version 2^>nul') do set "NODE_VERSION=%%V"

if not "%NODE_VERSION%"=="%NODE_VERSION_REQUIRED%" (
  echo.
  echo [ERROR] Node.js version is still incorrect.
  echo Required: %NODE_VERSION_REQUIRED%
  echo Detected: %NODE_VERSION%
  echo Check duplicate node.exe paths or uninstall the incompatible version.
  pause
  exit /b 14
)

:RUN_AUDIT
echo.
echo Validated Node.js: %NODE_VERSION_REQUIRED%
echo Running PC Audit...
node "%~dp0PC_Audit.js"
set "AUDIT_EXIT=%ERRORLEVEL%"
if not "%AUDIT_EXIT%"=="0" (
  echo.
  echo PC Audit failed. Exit code: %AUDIT_EXIT%
  echo If JSON was created, it is in the output folder.
  pause
)
endlocal & exit /b %AUDIT_EXIT%
