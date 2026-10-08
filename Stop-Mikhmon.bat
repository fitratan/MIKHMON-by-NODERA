@echo off
title Stop Mikhmon Desktop Server
echo Stopping all running Mikhmon PHP background processes...
taskkill /F /IM php.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8080 " 2^>nul') do (
    taskkill /F /PID %%a >nul 2>&1
)
echo Mikhmon server stopped successfully.
timeout /t 2 /nobreak >nul