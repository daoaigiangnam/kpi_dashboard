@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title MSTAR PC Audit Tool - Windows 7 / Node.js 12.22.12

set "NODE_VERSION_REQUIRED=v12.22.12"
set "NODE_MSI=node-v12.22.12-x64.msi"
set "NODE_URL=https://nodejs.org/dist/v12.22.12/node-v12.22.12-x64.msi"

rem Detect Windows architecture, including 32-bit process on 64-bit Windows.
if /i "%PROCESSOR_ARCHITEW6432%"=="AMD64" goto ARCH_X64
if /i "%PROCESSOR_ARCHITECTURE%"=="AMD64" goto ARCH_X64
if /i "%PROCESSOR_ARCHITECTURE%"=="IA64" goto ARCH_X64
goto ARCH_X86

:ARCH_X64
set "NODE_MSI=node-v12.22.12-x64.msi"
set "NODE_URL=https://nodejs.org/dist/v12.22.12/node-v12.22.12-x64.msi"
goto CHECK_NODE

:ARCH_X86
set "NODE_MSI=node-v12.22.12-x86.msi"
set "NODE_URL=https://nodejs.org/dist/v12.22.12/node-v12.22.12-x86.msi"

:CHECK_NODE
echo ==============================================
echo   MSTAR PC AUDIT - WINDOWS 7
echo   Node.js 12.22.12 auto installer
echo ==============================================
echo.

set "NODE_EXE="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"

if defined NODE_EXE (
  for /f "delims=" %%V in ('node --version 2^>nul') do set "NODE_VERSION=%%V"
  if "%NODE_VERSION%"=="%NODE_VERSION_REQUIRED%" goto RUN_AUDIT
  echo Found Node.js %NODE_VERSION%, but required version is %NODE_VERSION_REQUIRED%.
  echo Installing the compatible version...
) else (
  echo Node.js is not installed. Preparing automatic installation...
)

rem MSI installation needs Administrator privileges.
net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo [ERROR] Please right-click PC_Audit.bat and choose Run as administrator.
  pause
  exit /b 10
)

set "TEMP_MSI=%TEMP%\%NODE_MSI%"
if exist "%TEMP_MSI%" del /f /q "%TEMP_MSI%" >nul 2>&1

echo.
echo Downloading official Node.js installer:
echo %NODE_URL%
echo.

rem Try PowerShell with TLS 1.2 first. Do not bypass certificate validation.
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $wc = New-Object Net.WebClient; $wc.DownloadFile('%NODE_URL%','%TEMP_MSI%')" >nul 2>&1

if not exist "%TEMP_MSI%" (
  echo PowerShell download failed. Trying Windows certutil...
  certutil -urlcache -split -f "%NODE_URL%" "%TEMP_MSI%" >nul 2>&1
)

if not exist "%TEMP_MSI%" (
  echo.
  echo [ERROR] Automatic download failed. Windows 7 may have an outdated
  echo certificate store or be unable to validate nodejs.org.
  echo.
  echo Open the official download page and install the MSI manually:
  echo %NODE_URL%
  start "" "%NODE_URL%"
  echo After installation, close this window and run PC_Audit.bat again.
  pause
  exit /b 11
)

for %%F in ("%TEMP_MSI%") do if %%~zF LSS 100000 (
  del /f /q "%TEMP_MSI%" >nul 2>&1
  echo.
  echo [ERROR] Downloaded installer is incomplete.
  echo Please download the MSI manually from:
  echo %NODE_URL%
  start "" "%NODE_URL%"
  pause
  exit /b 12
)

echo Installing Node.js 12.22.12. Please wait...
msiexec.exe /i "%TEMP_MSI%" /passive /norestart
if errorlevel 1 (
  echo.
  echo [ERROR] Node.js installer returned an error.
  echo Run the MSI manually as Administrator: %TEMP_MSI%
  pause
  exit /b 13
)

del /f /q "%TEMP_MSI%" >nul 2>&1

rem Refresh PATH for this process; standard MSI location for x86/x64.
if /i "%PROCESSOR_ARCHITEW6432%"=="AMD64" set "PATH=%ProgramFiles%\nodejs;%ProgramFiles(x86)%\nodejs;%PATH%"
if /i "%PROCESSOR_ARCHITECTURE%"=="AMD64" set "PATH=%ProgramFiles%\nodejs;%ProgramFiles(x86)%\nodejs;%PATH%"
if /i "%PROCESSOR_ARCHITECTURE%"=="x86" set "PATH=%ProgramFiles%\nodejs;%PATH%"

set "NODE_EXE="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if not defined NODE_EXE (
  echo.
  echo Node.js installed, but node.exe is not yet available in PATH.
  echo Close this window, open a new CMD, then run PC_Audit.bat again.
  pause
  exit /b 14
)

for /f "delims=" %%V in ('node --version 2^>nul') do set "NODE_VERSION=%%V"
if not "%NODE_VERSION%"=="%NODE_VERSION_REQUIRED%" (
  echo.
  echo [ERROR] Detected Node.js %NODE_VERSION%; expected %NODE_VERSION_REQUIRED%.
  echo Close the window and check the Node.js installation.
  pause
  exit /b 15
)

:RUN_AUDIT
echo.
echo Node.js version: %NODE_VERSION%
echo Starting PC Audit...
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
