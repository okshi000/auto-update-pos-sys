@echo off
chcp 65001 >nul
title POS System
color 0A

set "BASE_DIR=%~dp0"
set "BACKEND_DIR=%BASE_DIR%backend"

echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                      POS System Launcher                         ║
echo ║                       تشغيل نظام نقاط البيع                      ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

:: Check if XAMPP services are running
echo Checking services...
echo فحص الخدمات...
echo.

:: Check Apache
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if %errorLevel% neq 0 (
    echo [!] Apache is not running
    echo    Starting XAMPP Apache...
    start "" "C:\xampp\xampp_start.exe" 2>nul
    timeout /t 3 >nul
)

:: Check MySQL
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if %errorLevel% neq 0 (
    echo [!] MySQL is not running
    echo    Please start MySQL from XAMPP Control Panel
)

echo.
echo Choose startup option:
echo اختر طريقة التشغيل:
echo.
echo [1] Start with PHP built-in server (Development)
echo     التشغيل بخادم PHP المدمج (للتطوير)
echo.
echo [2] Open in browser (Apache must be configured)
echo     فتح في المتصفح (يجب إعداد Apache)
echo.
echo [3] Start both backend and frontend dev servers
echo     تشغيل خوادم التطوير للواجهتين
echo.
set /p CHOICE="Enter choice (1-3) / أدخل اختيارك: "

if "%CHOICE%"=="1" (
    cd /d "%BACKEND_DIR%"
    echo.
    echo Starting server at http://localhost:8000
    echo تشغيل الخادم على http://localhost:8000
    echo.
    echo Press Ctrl+C to stop / اضغط Ctrl+C للإيقاف
    echo.
    start "" http://localhost:8000
    php artisan serve
) else if "%CHOICE%"=="2" (
    echo.
    echo Opening http://localhost/POS/backend/public
    start "" http://localhost/POS/backend/public
) else if "%CHOICE%"=="3" (
    echo.
    echo Starting development servers...
    echo تشغيل خوادم التطوير...
    echo.
    
    :: Start backend in new window
    start "POS Backend" cmd /k "cd /d %BACKEND_DIR% && php artisan serve"
    
    :: Start frontend in new window
    start "POS Frontend" cmd /k "cd /d %BASE_DIR%frontend && npm run dev"
    
    echo.
    echo Backend: http://localhost:8000
    echo Frontend: http://localhost:5173
    echo.
    timeout /t 5 >nul
    start "" http://localhost:5173
) else (
    echo Invalid choice
)

pause
