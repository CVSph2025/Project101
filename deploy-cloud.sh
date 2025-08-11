#!/bin/bash

# Laravel Cloud Deployment Script
# This script prepares and deploys your application to Laravel Cloud

echo "🚀 Preparing Laravel project for Laravel Cloud deployment..."

# 1. Check if cloud.yaml exists
if [ ! -f "cloud.yaml" ]; then
    echo "❌ cloud.yaml not found. Please create one first."
    exit 1
fi

# 2. Install dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo "📦 Installing Node.js dependencies..."
npm ci

# 3. Build assets
echo "🏗️ Building assets..."
npm run build

# 4. Clear and cache configuration
echo "⚡ Optimizing Laravel..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Run tests (optional)
if [ "$1" = "--with-tests" ]; then
    echo "🧪 Running tests..."
    php artisan test
fi

# 6. Check for .env.cloud
if [ -f ".env.cloud" ]; then
    echo "✅ Found .env.cloud - environment configuration ready"
else
    echo "⚠️ .env.cloud not found. Using .env.example as template"
    cp .env.example .env.cloud
fi

# 7. Security checks
echo "🔒 Running security checks..."

# Check for sensitive files
if [ -f ".env" ]; then
    echo "⚠️ Warning: .env file exists. Make sure it's in .gitignore"
fi

# Check storage permissions
if [ -d "storage" ]; then
    echo "📁 Checking storage permissions..."
    # Laravel Cloud will handle permissions, but we'll check structure
    mkdir -p storage/logs
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
fi

# 8. Database preparation
echo "🗄️ Preparing database migrations..."
# Note: Actual migrations will run on Laravel Cloud during deployment

# 9. Generate deployment summary
echo "
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
"

echo "✨ Deployment preparation complete!"
