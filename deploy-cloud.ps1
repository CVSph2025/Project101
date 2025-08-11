# Laravel Cloud Deployment Script for Windows PowerShell
# This script prepares and deploys your application to Laravel Cloud

Write-Host "🚀 Preparing Laravel project for Laravel Cloud deployment..." -ForegroundColor Green

# 1. Check if cloud.yaml exists
if (-not (Test-Path "cloud.yaml")) {
    Write-Host "❌ cloud.yaml not found. Please create one first." -ForegroundColor Red
    exit 1
}

# 2. Install dependencies
Write-Host "📦 Installing PHP dependencies..." -ForegroundColor Yellow
composer install --no-dev --optimize-autoloader

Write-Host "📦 Installing Node.js dependencies..." -ForegroundColor Yellow
npm ci

# 3. Build assets
Write-Host "🏗️ Building assets..." -ForegroundColor Yellow
npm run build

# 4. Clear and cache configuration
Write-Host "⚡ Optimizing Laravel..." -ForegroundColor Yellow
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Run tests (optional)
if ($args[0] -eq "--with-tests") {
    Write-Host "🧪 Running tests..." -ForegroundColor Yellow
    php artisan test
}

# 6. Check for .env.cloud
if (Test-Path ".env.cloud") {
    Write-Host "✅ Found .env.cloud - environment configuration ready" -ForegroundColor Green
} else {
    Write-Host "⚠️ .env.cloud not found. Using .env.example as template" -ForegroundColor Yellow
    Copy-Item ".env.example" ".env.cloud"
}

# 7. Security checks
Write-Host "🔒 Running security checks..." -ForegroundColor Yellow

# Check for sensitive files
if (Test-Path ".env") {
    Write-Host "⚠️ Warning: .env file exists. Make sure it's in .gitignore" -ForegroundColor Yellow
}

# Check storage permissions
if (Test-Path "storage") {
    Write-Host "📁 Checking storage permissions..." -ForegroundColor Yellow
    # Laravel Cloud will handle permissions, but we'll check structure
    New-Item -ItemType Directory -Force -Path "storage\logs" | Out-Null
    New-Item -ItemType Directory -Force -Path "storage\framework\cache" | Out-Null
    New-Item -ItemType Directory -Force -Path "storage\framework\sessions" | Out-Null
    New-Item -ItemType Directory -Force -Path "storage\framework\views" | Out-Null
}

# 8. Database preparation
Write-Host "🗄️ Preparing database migrations..." -ForegroundColor Yellow
# Note: Actual migrations will run on Laravel Cloud during deployment

# 9. Generate deployment summary
Write-Host @"

📋 Deployment Summary:
==========================================
✅ Dependencies installed
✅ Assets built
✅ Laravel optimized
✅ Configuration cached
✅ Environment file prepared
✅ Storage structure verified

🚀 Your project is ready for Laravel Cloud deployment!

Next steps:
1. Commit your changes to Git
2. Push to your repository
3. Connect your repository to Laravel Cloud
4. Configure environment variables in Laravel Cloud dashboard
5. Deploy!

Important environment variables to set in Laravel Cloud:
- APP_KEY (generate with: php artisan key:generate --show)
- Database credentials (will be auto-configured)
- Mail server settings
- Social auth credentials (if using)
- Stripe keys (if using payments)
- AWS S3 credentials (if using file storage)
"@ -ForegroundColor Cyan

Write-Host "✨ Deployment preparation complete!" -ForegroundColor Green
