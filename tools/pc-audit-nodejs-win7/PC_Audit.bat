@echo off
rem Relaunch in a persistent console. This keeps startup and install errors visible.
if /i not "%~1"=="__MSTAR_KEEP_OPEN__" (
  start "MSTAR PC Audit - Console" "%ComSpec%" /k ""%~f0" __MSTAR_KEEP_OPEN__"
  exit /b
)
setlocal EnableExtensions EnableDelayedExpansion
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

set "NODE_MSI=node-%NODE_VERSION_REQUIRED%-%ARCH%.msi"
set "NODE_URL=%NODE_URL_BASE%%NODE_MSI%"

echo Required Node.js: %NODE_VERSION_REQUIRED%
echo Architecture: %ARCH%
echo.

rem Detect installed Node.js. Skip install only when exact required version matches.
set "NODE_EXE="
set "NODE_VERSION="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if defined NODE_EXE (
  "!NODE_EXE!" --version > "%TEMP%\mstar_node_version.txt" 2>nul
  if exist "%TEMP%\mstar_node_version.txt" set /p NODE_VERSION=<"%TEMP%\mstar_node_version.txt"
  if exist "%TEMP%\mstar_node_version.txt" del /q "%TEMP%\mstar_node_version.txt" >nul 2>&1
  echo Found Node.js: !NODE_VERSION!
  echo Path: !NODE_EXE!
  if "!NODE_VERSION!"=="!NODE_VERSION_REQUIRED!" (
    echo Version matches. Skipping Node.js installation.
    goto RUN_AUDIT
  )
  echo.
  echo [WARNING] Installed Node.js version does not match the required version.
  echo Required: %NODE_VERSION_REQUIRED%
  echo Installed: !NODE_VERSION!
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
  echo Please check network access and TLS 1.2 support, then run this BAT again.
  pause
  exit /b 11
)
for %%F in ("%TEMP_MSI%") do if %%~zF LSS 100000 (
  del /f /q "%TEMP_MSI%" >nul 2>&1
  echo [ERROR] MSI download is incomplete:
  echo %NODE_URL%
  echo Please check network access and TLS 1.2 support, then run this BAT again.
  pause
  exit /b 12
)

echo Installing Node.js %NODE_VERSION_REQUIRED%...
msiexec.exe /i "%TEMP_MSI%" /qn /norestart
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
if defined NODE_EXE (
  "!NODE_EXE!" --version > "%TEMP%\mstar_node_version.txt" 2>nul
  if exist "%TEMP%\mstar_node_version.txt" set /p NODE_VERSION=<"%TEMP%\mstar_node_version.txt"
  if exist "%TEMP%\mstar_node_version.txt" del /q "%TEMP%\mstar_node_version.txt" >nul 2>&1
)

if not "!NODE_VERSION!"=="!NODE_VERSION_REQUIRED!" (
  echo.
  echo [ERROR] Node.js version is still incorrect.
  echo Required: %NODE_VERSION_REQUIRED%
  echo Detected: !NODE_VERSION!
  echo Check duplicate node.exe paths or uninstall the incompatible version.
  pause
  exit /b 14
)

:RUN_AUDIT
echo.
echo [1] Chay Audit day du ngay bay gio (nen chay lan dau)
echo [2] Cai dat Windows Service (lich lay tu Backend)
echo [3] Dung Windows Service
echo [4] Go Windows Service
echo [5] Xem trang thai Service
echo [6] Thoat
echo.
set "CHOICE="
set /p CHOICE=Chon (1-6): 
if "%CHOICE%"=="2" goto INSTALL_SERVICE
if "%CHOICE%"=="3" goto STOP_SERVICE
if "%CHOICE%"=="4" goto REMOVE_SERVICE
if "%CHOICE%"=="5" goto STATUS_SERVICE
if "%CHOICE%"=="6" exit /b 0
if not "%CHOICE%"=="1" goto RUN_AUDIT
node "%~dp0PC_Audit.js"
set "AUDIT_EXIT=%ERRORLEVEL%"
if not "%AUDIT_EXIT%"=="0" (
  echo.
  echo PC Audit failed. Exit code: %AUDIT_EXIT%
  echo If JSON was created, it is in the output folder.
  pause
)
echo.
echo Audit da ket thuc. Quay lai menu chinh...
timeout /t 2 /nobreak >nul
goto RUN_AUDIT

:INSTALL_SERVICE
if not exist "%~dp0output" mkdir "%~dp0output"
net session >nul 2>&1
if errorlevel 1 goto INSTALL_NEEDS_ADMIN
if exist "%~dp0nssm.exe" goto NSSM_READY
set "NSSM_ARCH=win32"
if /i "%ARCH%"=="x64" set "NSSM_ARCH=win64"
echo Dang tai NSSM tu MSTAR Server (!NSSM_ARCH!)...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; $u='https://kpi.review360.id.vn/nssm/!NSSM_ARCH!/nssm.exe'; $p='%~dp0nssm.exe'; try { (New-Object Net.WebClient).DownloadFile($u,$p); $f=Get-Item $p; if($f.Length -lt 50000){Remove-Item $p -Force; throw 'File NSSM tai ve qua nho'} } catch { if(Test-Path $p){Remove-Item $p -Force -ErrorAction SilentlyContinue}; Write-Host ('Tai tu MSTAR Server that bai: '+$_.Exception.Message); exit 1 }"
if not errorlevel 1 goto NSSM_READY
echo Khong tai duoc tu MSTAR Server. Thu nguon du phong...
set "NSSM_ZIP=%TEMP%\mstar-nssm.zip"
if exist "%NSSM_ZIP%" del /f /q "%NSSM_ZIP%" >nul 2>&1
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; $out=$env:TEMP+'\mstar-nssm.zip'; $urls=@('https://github.com/tuyndoan/nssm/releases/download/v2.25/nssm-2.25.zip','https://nssm.cc/release/nssm-2.24.zip'); foreach($u in $urls){ for($i=1;$i -le 2;$i++){ try { Write-Host ('Downloading '+$u+' attempt '+$i); if(Test-Path $out){Remove-Item $out -Force}; $wc=New-Object Net.WebClient; $wc.Headers.Add('User-Agent','MSTAR-PC-Audit/1.0'); $wc.DownloadFile($u,$out); $z=[IO.Compression.ZipFile]::OpenRead($out); $ok=($z.Entries | Where-Object {$_.FullName -match '(^|/)win(32|64)/nssm\.exe$'}).Count -gt 0; $z.Dispose(); if($ok){exit 0}; throw 'ZIP khong hop le' } catch { Write-Host ('Download failed: '+$_.Exception.Message); Start-Sleep -Seconds 2 } } }; exit 1"
if errorlevel 1 goto NSSM_DOWNLOAD_FAILED
echo Dang giai nen NSSM...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; Add-Type -AssemblyName System.IO.Compression.FileSystem; $zip=$env:TEMP+'\mstar-nssm.zip'; $dest=$env:TEMP+'\mstar-nssm-extract'; if(Test-Path $dest){Remove-Item $dest -Recurse -Force}; [IO.Compression.ZipFile]::ExtractToDirectory($zip,$dest)"
if errorlevel 1 goto NSSM_EXTRACT_FAILED
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $root=$env:TEMP+'\mstar-nssm-extract'; $arch='!NSSM_ARCH!'; $f=Get-ChildItem -Path $root -Filter nssm.exe -Recurse | Where-Object {$_.FullName -match ('\\'+$arch+'\\nssm\.exe$')} | Select-Object -First 1; if(!$f){Write-Error ('Khong tim thay NSSM cho '+$arch); exit 1}; Copy-Item $f.FullName '%~dp0nssm.exe' -Force"
if errorlevel 1 goto NSSM_COPY_FAILED
:NSSM_READY
echo Da chuan bi NSSM thanh cong.
echo Dang cau hinh Audit Code va thong tin nguoi dung...
node "%~dp0PC_Audit.js" --setup-service
if errorlevel 1 goto SERVICE_CONFIG_FAILED
set "NODE_EXE="
for /f "delims=" %%N in ('where node 2^>nul') do if not defined NODE_EXE set "NODE_EXE=%%N"
if not defined NODE_EXE goto NODE_NOT_FOUND
"%~dp0nssm.exe" install MSTAR_PCAudit "%NODE_EXE%" "%~dp0PC_Audit.js" --service-loop
if errorlevel 1 goto NSSM_INSTALL_FAILED
"%~dp0nssm.exe" set MSTAR_PCAudit AppDirectory "%~dp0"
"%~dp0nssm.exe" set MSTAR_PCAudit DisplayName "MSTAR PC Audit Service"
"%~dp0nssm.exe" set MSTAR_PCAudit Description "Dinh ky cap nhat inventory PC len MSTAR Audit Server"
"%~dp0nssm.exe" set MSTAR_PCAudit Start SERVICE_AUTO_START
"%~dp0nssm.exe" set MSTAR_PCAudit AppStdout "%~dp0output\service-stdout.log"
"%~dp0nssm.exe" set MSTAR_PCAudit AppStderr "%~dp0output\service-stderr.log"
"%~dp0nssm.exe" set MSTAR_PCAudit AppNoConsole 1
"%~dp0nssm.exe" start MSTAR_PCAudit
if errorlevel 1 goto SERVICE_START_FAILED
echo.
echo Da cai dat Windows Service MSTAR_PCAudit.
echo Schedule do Backend quan ly; Service lay cau hinh moi 5 phut. Log trong thu muc output.
pause
goto RUN_AUDIT

:INSTALL_NEEDS_ADMIN
echo [ERROR] Hay chay PC_Audit.bat bang Run as administrator.
pause
goto RUN_AUDIT

:NSSM_DOWNLOAD_FAILED
echo [ERROR] Khong tai duoc NSSM tu MSTAR Server va nguon du phong.
pause
goto RUN_AUDIT

:NSSM_EXTRACT_FAILED
echo [ERROR] Khong giai nen duoc file NSSM.
pause
goto RUN_AUDIT

:NSSM_COPY_FAILED
echo [ERROR] Khong tim thay hoac sao chep duoc NSSM !NSSM_ARCH!.
pause
goto RUN_AUDIT

:SERVICE_CONFIG_FAILED
echo [ERROR] Khong cau hinh duoc Service. Kiem tra Audit Code va ket noi Server.
pause
goto RUN_AUDIT

:NODE_NOT_FOUND
echo [ERROR] Khong tim thay node.exe.
pause
goto RUN_AUDIT

:NSSM_INSTALL_FAILED
echo [ERROR] NSSM khong tao duoc Service. Kiem tra Service da ton tai chua.
pause
goto RUN_AUDIT

:SERVICE_START_FAILED
echo [ERROR] Da tao Service nhung khong khoi dong duoc. Kiem tra output\service-stderr.log.
pause
goto RUN_AUDIT

:STOP_SERVICE
net session >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Hay chay bang Run as administrator.
  pause
  goto RUN_AUDIT
)
"%~dp0nssm.exe" stop MSTAR_PCAudit
pause
goto RUN_AUDIT

:REMOVE_SERVICE
net session >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Hay chay bang Run as administrator.
  pause
  goto RUN_AUDIT
)
node "%~dp0PC_Audit.js" --remove-service
pause
goto RUN_AUDIT

:STATUS_SERVICE
node "%~dp0PC_Audit.js" --service-status
pause
goto RUN_AUDIT

