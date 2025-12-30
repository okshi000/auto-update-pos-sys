@echo off
chcp 65001 >nul
title POS System Installer
color 0A

echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                    POS System Installer                          ║
echo ║                     نظام نقاط البيع                              ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

:: Check for Administrator privileges
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Please run this installer as Administrator
    echo [خطأ] يرجى تشغيل المثبت كمسؤول
    echo.
    echo Right-click on install.bat and select "Run as administrator"
    pause
    exit /b 1
)

:: Set installation directory
set "INSTALL_DIR=%~dp0"
cd /d "%INSTALL_DIR%"

echo [1/8] Checking system requirements...
echo [1/8] فحص متطلبات النظام...
echo.

:: Check if PHP is installed
where php >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] PHP is not installed or not in PATH
    echo [خطأ] PHP غير مثبت أو غير موجود في PATH
    echo.
    echo Please install XAMPP from: https://www.apachefriends.org/
    pause
    exit /b 1
)

:: Check PHP version
for /f "tokens=2 delims= " %%i in ('php -v 2^>^&1 ^| findstr /i "PHP"') do set PHP_VERSION=%%i
echo    PHP Version: %PHP_VERSION%

:: Check if Node.js is installed
where node >nul 2>&1
if %errorLevel% neq 0 (
    echo [WARNING] Node.js is not installed - Frontend build may fail
    echo [تحذير] Node.js غير مثبت - قد يفشل بناء الواجهة
) else (
    for /f "tokens=1 delims=" %%i in ('node -v') do set NODE_VERSION=%%i
    echo    Node.js Version: %NODE_VERSION%
)

:: Check if MySQL is running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if %errorLevel% neq 0 (
    echo [WARNING] MySQL is not running
    echo [تحذير] MySQL غير مشغل
    echo    Please start XAMPP and run MySQL
)

echo.
echo [2/8] Creating necessary directories...
echo [2/8] إنشاء المجلدات اللازمة...

if not exist "%INSTALL_DIR%logs" mkdir "%INSTALL_DIR%logs"
if not exist "%INSTALL_DIR%backups" mkdir "%INSTALL_DIR%backups"
if not exist "%INSTALL_DIR%backend\storage\logs" mkdir "%INSTALL_DIR%backend\storage\logs"
if not exist "%INSTALL_DIR%backend\storage\framework\cache" mkdir "%INSTALL_DIR%backend\storage\framework\cache"
if not exist "%INSTALL_DIR%backend\storage\framework\sessions" mkdir "%INSTALL_DIR%backend\storage\framework\sessions"
if not exist "%INSTALL_DIR%backend\storage\framework\views" mkdir "%INSTALL_DIR%backend\storage\framework\views"

echo    Directories created successfully
echo.

echo [3/8] Setting up environment configuration...
echo [3/8] إعداد ملف التكوين...

cd /d "%INSTALL_DIR%backend"

if not exist ".env" (
    if exist ".env.example" (
        copy ".env.example" ".env" >nul
        echo    Created .env from .env.example
    ) else (
        echo [ERROR] .env.example not found
        pause
        exit /b 1
    )
) else (
    echo    .env file already exists
)

echo.
echo [4/8] Installing PHP dependencies...
echo [4/8] تثبيت مكتبات PHP...

where composer >nul 2>&1
if %errorLevel% neq 0 (
    echo [WARNING] Composer not found, trying php composer.phar...
    if exist "composer.phar" (
        php composer.phar install --no-dev --optimize-autoloader
    ) else (
        echo [ERROR] Composer is required. Please install from: https://getcomposer.org/
        pause
        exit /b 1
    )
) else (
    call composer install --no-dev --optimize-autoloader
)

echo.
echo [5/8] Generating application key...
echo [5/8] إنشاء مفتاح التطبيق...

php artisan key:generate --force
echo    Application key generated

echo.
echo [6/8] Setting up database...
echo [6/8] إعداد قاعدة البيانات...

echo.
echo Choose database setup option:
echo اختر طريقة إعداد قاعدة البيانات:
echo.
echo [1] Fresh installation (create new database)
echo     تثبيت جديد (إنشاء قاعدة بيانات جديدة)
echo.
echo [2] Restore from backup
echo     استعادة من نسخة احتياطية
echo.
set /p DB_CHOICE="Enter choice (1 or 2) / أدخل اختيارك: "

if "%DB_CHOICE%"=="1" (
    echo    Running migrations...
    php artisan migrate --force
    echo    Running seeders...
    php artisan db:seed --force
    echo    Database setup completed
) else if "%DB_CHOICE%"=="2" (
    echo.
    echo Available backups:
    echo النسخ الاحتياطية المتوفرة:
    dir /b "%INSTALL_DIR%backups\*.sql" 2>nul
    echo.
    set /p BACKUP_FILE="Enter backup filename / أدخل اسم ملف النسخة: "
    if exist "%INSTALL_DIR%backups\%BACKUP_FILE%" (
        echo    Restoring database...
        mysql -u root < "%INSTALL_DIR%backups\%BACKUP_FILE%"
        echo    Database restored
    ) else (
        echo [ERROR] Backup file not found
        echo    Running fresh migration instead...
        php artisan migrate --force
        php artisan db:seed --force
    )
) else (
    echo    Invalid choice, running fresh installation...
    php artisan migrate --force
    php artisan db:seed --force
)

echo.
echo [7/8] Building frontend...
echo [7/8] بناء الواجهة الأمامية...

cd /d "%INSTALL_DIR%frontend"

if exist "node_modules" (
    echo    Node modules already installed
) else (
    where npm >nul 2>&1
    if %errorLevel% equ 0 (
        call npm install
    ) else (
        echo [WARNING] npm not found, skipping frontend build
        goto skip_frontend
    )
)

call npm run build

:: Copy built files to backend public
if exist "dist" (
    echo    Copying frontend build to backend...
    xcopy /E /Y /I "dist\*" "%INSTALL_DIR%backend\public\" >nul
    echo    Frontend deployed successfully
)

:skip_frontend

echo.
echo [8/8] Final configuration...
echo [8/8] الإعدادات النهائية...

cd /d "%INSTALL_DIR%backend"

:: Clear and cache configuration
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache

:: Set proper permissions (Windows)
icacls "%INSTALL_DIR%backend\storage" /grant Users:F /T >nul 2>&1
icacls "%INSTALL_DIR%backend\bootstrap\cache" /grant Users:F /T >nul 2>&1

echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║              Installation Completed Successfully!                ║
echo ║                    اكتمل التثبيت بنجاح!                          ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.
echo Next steps / الخطوات التالية:
echo.
echo 1. Configure Apache virtual host or use built-in server
echo    قم بإعداد Apache أو استخدم الخادم المدمج
echo.
echo 2. Run: php artisan serve
echo    لتشغيل الخادم المدمج
echo.
echo 3. Access: http://localhost:8000
echo    للوصول إلى النظام
echo.
echo 4. Default login:
echo    البريد الافتراضي: admin@example.com
echo    كلمة المرور: password
echo.

set /p START_SERVER="Start server now? (Y/N) / تشغيل الخادم الآن؟: "
if /i "%START_SERVER%"=="Y" (
    echo.
    echo Starting server at http://localhost:8000
    echo اضغط Ctrl+C لإيقاف الخادم
    php artisan serve
)

pause
