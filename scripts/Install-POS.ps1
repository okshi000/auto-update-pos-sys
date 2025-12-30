# ============================================
# POS System - Advanced Installer (PowerShell)
# نظام نقاط البيع - مثبت متقدم
# ============================================

#Requires -RunAsAdministrator

param(
    [switch]$Silent,
    [string]$DatabaseName = "pos_database",
    [string]$DatabaseUser = "root",
    [string]$DatabasePassword = ""
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "Continue"

# Colors
function Write-ColorOutput($ForegroundColor) {
    $fc = $host.UI.RawUI.ForegroundColor
    $host.UI.RawUI.ForegroundColor = $ForegroundColor
    if ($args) {
        Write-Output $args
    }
    $host.UI.RawUI.ForegroundColor = $fc
}

function Write-Header {
    Clear-Host
    Write-Host ""
    Write-Host "╔══════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║                    POS System Installer                          ║" -ForegroundColor Cyan
    Write-Host "║                     نظام نقاط البيع                              ║" -ForegroundColor Cyan
    Write-Host "╚══════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
}

function Write-Step($step, $total, $message, $messageAr) {
    Write-Host "[$step/$total] " -ForegroundColor Yellow -NoNewline
    Write-Host "$message" -ForegroundColor White
    Write-Host "        $messageAr" -ForegroundColor DarkGray
    Write-Host ""
}

function Write-Success($message) {
    Write-Host "  ✓ $message" -ForegroundColor Green
}

function Write-Warning($message) {
    Write-Host "  ⚠ $message" -ForegroundColor Yellow
}

function Write-Error($message) {
    Write-Host "  ✗ $message" -ForegroundColor Red
}

function Test-Command($command) {
    try {
        Get-Command $command -ErrorAction Stop | Out-Null
        return $true
    } catch {
        return $false
    }
}

function Get-XamppPath {
    $paths = @(
        "C:\xampp",
        "D:\xampp",
        "$env:ProgramFiles\xampp",
        "${env:ProgramFiles(x86)}\xampp"
    )
    
    foreach ($path in $paths) {
        if (Test-Path "$path\php\php.exe") {
            return $path
        }
    }
    return $null
}

# Main Installation
Write-Header

$BaseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BackendDir = Join-Path $BaseDir "backend"
$FrontendDir = Join-Path $BaseDir "frontend"
$LogsDir = Join-Path $BaseDir "logs"
$BackupsDir = Join-Path $BaseDir "backups"

# Create log file
$LogFile = Join-Path $LogsDir "install_$(Get-Date -Format 'yyyy-MM-dd_HH-mm-ss').log"
if (!(Test-Path $LogsDir)) { New-Item -ItemType Directory -Path $LogsDir -Force | Out-Null }

function Log($message) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $LogFile -Value "[$timestamp] $message"
}

Log "Installation started"

# Step 1: Check Requirements
Write-Step 1 8 "Checking system requirements..." "فحص متطلبات النظام..."

# Check PHP
$xamppPath = Get-XamppPath
if ($xamppPath) {
    $phpPath = Join-Path $xamppPath "php\php.exe"
    $env:Path = "$xamppPath\php;$xamppPath\mysql\bin;$env:Path"
    Write-Success "XAMPP found at: $xamppPath"
    Log "XAMPP found at: $xamppPath"
} elseif (Test-Command "php") {
    Write-Success "PHP found in PATH"
} else {
    Write-Error "PHP not found. Please install XAMPP first."
    Log "ERROR: PHP not found"
    exit 1
}

$phpVersion = php -v 2>&1 | Select-String -Pattern "PHP (\d+\.\d+)" | ForEach-Object { $_.Matches.Groups[1].Value }
Write-Success "PHP Version: $phpVersion"
Log "PHP Version: $phpVersion"

# Check Composer
if (Test-Command "composer") {
    Write-Success "Composer found"
} elseif (Test-Path "$BackendDir\composer.phar") {
    Write-Warning "Using local composer.phar"
} else {
    Write-Warning "Composer not found - will try to download"
}

# Check Git
if (Test-Command "git") {
    Write-Success "Git found"
} else {
    Write-Warning "Git not found - Updates will require manual installation"
}

# Check Node.js
if (Test-Command "node") {
    $nodeVersion = node -v
    Write-Success "Node.js found: $nodeVersion"
} else {
    Write-Warning "Node.js not found - Frontend build will be skipped"
}

# Step 2: Create Directories
Write-Step 2 8 "Creating directories..." "إنشاء المجلدات..."

$directories = @(
    $LogsDir,
    $BackupsDir,
    "$BackendDir\storage\logs",
    "$BackendDir\storage\framework\cache",
    "$BackendDir\storage\framework\sessions",
    "$BackendDir\storage\framework\views",
    "$BackendDir\bootstrap\cache"
)

foreach ($dir in $directories) {
    if (!(Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}
Write-Success "All directories created"
Log "Directories created"

# Step 3: Environment Setup
Write-Step 3 8 "Setting up environment..." "إعداد ملف التكوين..."

Set-Location $BackendDir

if (!(Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Success "Created .env from .env.example"
        Log "Created .env file"
    } else {
        Write-Error ".env.example not found"
        exit 1
    }
} else {
    Write-Success ".env file exists"
}

# Update .env with database settings
$envContent = Get-Content ".env" -Raw
$envContent = $envContent -replace "DB_DATABASE=.*", "DB_DATABASE=$DatabaseName"
$envContent = $envContent -replace "DB_USERNAME=.*", "DB_USERNAME=$DatabaseUser"
$envContent = $envContent -replace "DB_PASSWORD=.*", "DB_PASSWORD=$DatabasePassword"
Set-Content ".env" $envContent
Write-Success "Database configuration updated"

# Step 4: Install PHP Dependencies
Write-Step 4 8 "Installing PHP dependencies..." "تثبيت مكتبات PHP..."

try {
    if (Test-Command "composer") {
        composer install --no-dev --optimize-autoloader 2>&1 | Out-Null
    } else {
        php composer.phar install --no-dev --optimize-autoloader 2>&1 | Out-Null
    }
    Write-Success "PHP dependencies installed"
    Log "Composer install successful"
} catch {
    Write-Error "Failed to install dependencies: $_"
    Log "ERROR: Composer install failed - $_"
}

# Step 5: Generate Key
Write-Step 5 8 "Generating application key..." "إنشاء مفتاح التطبيق..."

php artisan key:generate --force 2>&1 | Out-Null
Write-Success "Application key generated"
Log "App key generated"

# Step 6: Database Setup
Write-Step 6 8 "Setting up database..." "إعداد قاعدة البيانات..."

if (!$Silent) {
    Write-Host ""
    Write-Host "  Choose database setup:" -ForegroundColor Cyan
    Write-Host "  اختر طريقة إعداد قاعدة البيانات:" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "  [1] Fresh installation (new database)" -ForegroundColor White
    Write-Host "  [2] Restore from backup" -ForegroundColor White
    Write-Host ""
    $dbChoice = Read-Host "  Enter choice (1 or 2)"
} else {
    $dbChoice = "1"
}

if ($dbChoice -eq "1") {
    try {
        Write-Host "  Running migrations..." -ForegroundColor Gray
        php artisan migrate --force 2>&1 | Out-Null
        Write-Host "  Running seeders..." -ForegroundColor Gray
        php artisan db:seed --force 2>&1 | Out-Null
        Write-Success "Database setup completed"
        Log "Database migrated and seeded"
    } catch {
        Write-Error "Database setup failed: $_"
        Log "ERROR: Database setup failed - $_"
    }
} elseif ($dbChoice -eq "2") {
    $backups = Get-ChildItem -Path $BackupsDir -Filter "*.sql" | Sort-Object LastWriteTime -Descending
    if ($backups.Count -gt 0) {
        Write-Host ""
        Write-Host "  Available backups:" -ForegroundColor Cyan
        for ($i = 0; $i -lt $backups.Count; $i++) {
            Write-Host "  [$($i+1)] $($backups[$i].Name)" -ForegroundColor White
        }
        $backupChoice = Read-Host "  Enter backup number"
        $selectedBackup = $backups[$backupChoice - 1].FullName
        
        try {
            mysql -u $DatabaseUser -p"$DatabasePassword" $DatabaseName < $selectedBackup
            Write-Success "Database restored from backup"
            Log "Database restored from: $selectedBackup"
        } catch {
            Write-Warning "Restore failed, running fresh migration..."
            php artisan migrate --force 2>&1 | Out-Null
            php artisan db:seed --force 2>&1 | Out-Null
        }
    } else {
        Write-Warning "No backups found, running fresh migration..."
        php artisan migrate --force 2>&1 | Out-Null
        php artisan db:seed --force 2>&1 | Out-Null
    }
}

# Step 7: Build Frontend
Write-Step 7 8 "Building frontend..." "بناء الواجهة الأمامية..."

if (Test-Command "npm") {
    Set-Location $FrontendDir
    
    try {
        if (!(Test-Path "node_modules")) {
            Write-Host "  Installing npm packages..." -ForegroundColor Gray
            npm install 2>&1 | Out-Null
        }
        
        Write-Host "  Building production bundle..." -ForegroundColor Gray
        npm run build 2>&1 | Out-Null
        
        if (Test-Path "dist") {
            Copy-Item -Path "dist\*" -Destination "$BackendDir\public\" -Recurse -Force
            Write-Success "Frontend built and deployed"
            Log "Frontend build successful"
        }
    } catch {
        Write-Warning "Frontend build failed: $_"
        Log "WARNING: Frontend build failed - $_"
    }
} else {
    Write-Warning "Skipping frontend build (npm not found)"
}

# Step 8: Final Configuration
Write-Step 8 8 "Final configuration..." "الإعدادات النهائية..."

Set-Location $BackendDir

# Clear and cache
php artisan config:clear 2>&1 | Out-Null
php artisan route:clear 2>&1 | Out-Null
php artisan view:clear 2>&1 | Out-Null
php artisan config:cache 2>&1 | Out-Null
php artisan route:cache 2>&1 | Out-Null

# Create storage link
php artisan storage:link 2>&1 | Out-Null

Write-Success "Configuration cached"
Write-Success "Storage link created"
Log "Final configuration completed"

# Create desktop shortcut
$WshShell = New-Object -ComObject WScript.Shell
$Shortcut = $WshShell.CreateShortcut("$env:USERPROFILE\Desktop\POS System.lnk")
$Shortcut.TargetPath = Join-Path $BaseDir "start.bat"
$Shortcut.WorkingDirectory = $BaseDir
$Shortcut.Description = "Launch POS System"
$Shortcut.Save()
Write-Success "Desktop shortcut created"

# Summary
Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║              Installation Completed Successfully!                ║" -ForegroundColor Green
Write-Host "║                    اكتمل التثبيت بنجاح!                          ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "  Next Steps:" -ForegroundColor Cyan
Write-Host "  الخطوات التالية:" -ForegroundColor DarkGray
Write-Host ""
Write-Host "  1. Double-click 'POS System' shortcut on desktop" -ForegroundColor White
Write-Host "     انقر على أيقونة 'POS System' على سطح المكتب" -ForegroundColor DarkGray
Write-Host ""
Write-Host "  2. Or run: " -ForegroundColor White -NoNewline
Write-Host "php artisan serve" -ForegroundColor Yellow
Write-Host ""
Write-Host "  3. Open: " -ForegroundColor White -NoNewline
Write-Host "http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "  4. Login with:" -ForegroundColor White
Write-Host "     Email: admin@example.com" -ForegroundColor Gray
Write-Host "     Password: password" -ForegroundColor Gray
Write-Host ""
Write-Host "  ⚠ Remember to change the default password!" -ForegroundColor Yellow
Write-Host "    تذكر تغيير كلمة المرور الافتراضية!" -ForegroundColor DarkYellow
Write-Host ""

Log "Installation completed successfully"

if (!$Silent) {
    $startNow = Read-Host "Start server now? (Y/N) / تشغيل الخادم الآن؟"
    if ($startNow -eq "Y" -or $startNow -eq "y") {
        Set-Location $BackendDir
        Start-Process "http://localhost:8000"
        php artisan serve
    }
}

Set-Location $BaseDir
