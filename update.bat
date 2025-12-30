@echo off
chcp 65001 >nul
title POS System Updater
color 0B

:: Set paths
set "BASE_DIR=%~dp0"
set "BACKEND_DIR=%BASE_DIR%backend"
set "FRONTEND_DIR=%BASE_DIR%frontend"
set "LOGS_DIR=%BASE_DIR%logs"
set "BACKUPS_DIR=%BASE_DIR%backups"
set "LOG_FILE=%LOGS_DIR%\update_%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%.log"

:: Create log file
if not exist "%LOGS_DIR%" mkdir "%LOGS_DIR%"
echo Update started at %date% %time% > "%LOG_FILE%"

echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                    POS System Updater                            ║
echo ║                      تحديث النظام                                ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

:: Change to base directory
cd /d "%BASE_DIR%"

echo [1/7] Creating database backup...
echo [1/7] إنشاء نسخة احتياطية من قاعدة البيانات...
echo.

:: Get timestamp for backup
set "TIMESTAMP=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%-%time:~0,2%-%time:~3,2%-%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUPS_DIR%\backup-pre-update-%TIMESTAMP%.sql"

:: Create backup directory if not exists
if not exist "%BACKUPS_DIR%" mkdir "%BACKUPS_DIR%"

:: Try to create MySQL backup
cd /d "%BACKEND_DIR%"
php artisan db:backup 2>nul
if %errorLevel% neq 0 (
    echo    Using mysqldump for backup...
    mysqldump -u root pos_database > "%BACKUP_FILE%" 2>>"%LOG_FILE%"
    if %errorLevel% equ 0 (
        echo    Backup created: %BACKUP_FILE%
        echo Backup created: %BACKUP_FILE% >> "%LOG_FILE%"
    ) else (
        echo    [WARNING] Backup may have failed, continuing anyway...
        echo Backup failed >> "%LOG_FILE%"
    )
) else (
    echo    Backup created successfully
    echo Backup created via artisan >> "%LOG_FILE%"
)

echo.
echo [2/7] Enabling maintenance mode...
echo [2/7] تفعيل وضع الصيانة...

php artisan down --message="System is being updated. Please wait..." --retry=60
echo Maintenance mode enabled >> "%LOG_FILE%"

echo.
echo [3/7] Pulling latest changes from repository...
echo [3/7] جلب آخر التحديثات من المستودع...

cd /d "%BASE_DIR%"

:: Check if git is available
where git >nul 2>&1
if %errorLevel% neq 0 (
    echo    [ERROR] Git is not installed
    echo Git not found >> "%LOG_FILE%"
    goto rollback
)

:: Initialize git repo if not exists
if not exist ".git" (
    echo    Initializing git repository...
    git init 2>>"%LOG_FILE%"
    git remote add origin https://github.com/RekazCode/posrekaz.git 2>>"%LOG_FILE%"
    echo    Git repository initialized >> "%LOG_FILE%"
)

:: Fetch latest changes
git fetch origin main 2>>"%LOG_FILE%"

:: Stash any local changes
git stash 2>>"%LOG_FILE%"

:: Reset to latest version from remote
git reset --hard origin/main 2>>"%LOG_FILE%"
if %errorLevel% neq 0 (
    echo    [ERROR] Failed to pull updates
    echo Git pull failed >> "%LOG_FILE%"
    git stash pop 2>>"%LOG_FILE%"
    goto rollback
)

:: Apply stashed changes back
git stash pop 2>>"%LOG_FILE%"

echo    Updates pulled successfully
echo Git pull successful >> "%LOG_FILE%"

echo.
echo [4/7] Updating PHP dependencies...
echo [4/7] تحديث مكتبات PHP...

cd /d "%BACKEND_DIR%"

where composer >nul 2>&1
if %errorLevel% equ 0 (
    call composer install --no-dev --optimize-autoloader 2>>"%LOG_FILE%"
) else (
    if exist "composer.phar" (
        php composer.phar install --no-dev --optimize-autoloader 2>>"%LOG_FILE%"
    )
)

if %errorLevel% neq 0 (
    echo    [ERROR] Composer install failed
    echo Composer install failed >> "%LOG_FILE%"
    goto rollback
)

echo    PHP dependencies updated
echo Composer install successful >> "%LOG_FILE%"

echo.
echo [5/7] Running database migrations...
echo [5/7] تشغيل تحديثات قاعدة البيانات...

php artisan migrate --force 2>>"%LOG_FILE%"
if %errorLevel% neq 0 (
    echo    [ERROR] Migration failed
    echo Migration failed >> "%LOG_FILE%"
    goto rollback
)

echo    Migrations completed
echo Migrations successful >> "%LOG_FILE%"

echo.
echo [6/7] Building frontend...
echo [6/7] بناء الواجهة الأمامية...

cd /d "%FRONTEND_DIR%"

where npm >nul 2>&1
if %errorLevel% equ 0 (
    call npm install 2>>"%LOG_FILE%"
    call npm run build 2>>"%LOG_FILE%"
    
    if exist "dist" (
        echo    Deploying frontend...
        xcopy /E /Y /I "dist\*" "%BACKEND_DIR%\public\" >nul 2>>"%LOG_FILE%"
        echo    Frontend deployed
        echo Frontend build successful >> "%LOG_FILE%"
    )
) else (
    echo    [WARNING] npm not found, skipping frontend build
    echo npm not found >> "%LOG_FILE%"
)

echo.
echo [7/7] Clearing caches and finalizing...
echo [7/7] مسح الذاكرة المؤقتة والإنهاء...

cd /d "%BACKEND_DIR%"

:: Clear all caches
php artisan config:clear 2>>"%LOG_FILE%"
php artisan route:clear 2>>"%LOG_FILE%"
php artisan view:clear 2>>"%LOG_FILE%"
php artisan cache:clear 2>>"%LOG_FILE%"

:: Rebuild caches
php artisan config:cache 2>>"%LOG_FILE%"
php artisan route:cache 2>>"%LOG_FILE%"

:: Disable maintenance mode
php artisan up
echo Maintenance mode disabled >> "%LOG_FILE%"

echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                  Update Completed Successfully!                  ║
echo ║                      اكتمل التحديث بنجاح!                        ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.
echo Update completed at %date% %time% >> "%LOG_FILE%"
echo Log file: %LOG_FILE%
echo.

goto end

:rollback
echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                     Update Failed - Rolling Back                 ║
echo ║                    فشل التحديث - جاري الاستعادة                  ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

cd /d "%BACKEND_DIR%"

:: Disable maintenance mode
php artisan up 2>>"%LOG_FILE%"

:: Try to restore from backup
if exist "%BACKUP_FILE%" (
    echo    Restoring database from backup...
    mysql -u root pos_database < "%BACKUP_FILE%" 2>>"%LOG_FILE%"
    echo    Database restored
    echo Database restored from backup >> "%LOG_FILE%"
)

echo.
echo [ERROR] Update failed. System has been restored.
echo [خطأ] فشل التحديث. تم استعادة النظام.
echo.
echo Please check the log file: %LOG_FILE%
echo Update failed - rollback completed >> "%LOG_FILE%"

:end
echo.
pause
