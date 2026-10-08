@echo off
title Mikhmon Desktop Standalone by NODERA
cd /d "%~dp0"
echo ================================================================
echo           MIKHMON DESKTOP STANDALONE by NODERA
echo ================================================================

:: Detect PHP Binary
set "PHP_BIN=%~dp0bin\php-win\php.exe"
if not exist "%PHP_BIN%" (
    where php >nul 2>&1
    if %errorlevel% equ 0 (
        set "PHP_BIN=php"
    ) else (
        echo [ERROR] PHP runtime not found in bin\php-win\php.exe.
        pause
        exit /b 1
    )
)

:: Detect Document Root
set "DOC_ROOT=%~dp0mikhmon"
if not exist "%DOC_ROOT%\index.php" (
    set "DOC_ROOT=%~dp0"
)

:: Kill any existing hanging process on port 8080
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8080 " 2^>nul') do (
    taskkill /F /PID %%a >nul 2>&1
)

echo Starting local web server on http://127.0.0.1:8080 ...
start "" "%PHP_BIN%" -S 127.0.0.1:8080 -t "%DOC_ROOT%"
timeout /t 2 /nobreak >nul

echo Opening default web browser...
start http://127.0.0.1:8080/admin.php?id=sessions

echo.
echo Mikhmon Desktop is active in background (http://127.0.0.1:8080).
echo Tutup jendela terminal ini untuk menghentikan server Mikhmon.
pause
taskkill /F /IM php.exe >nul 2>&1