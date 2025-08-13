#!/usr/bin/env php
<?php

/**
 * Enterprise Environment Setup Script
 * 
 * This script sets up a production-ready environment configuration
 * for the HomyGo application with security best practices.
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

echo "🏢 HomyGo Enterprise Environment Setup\n";
echo "=====================================\n\n";

// Check if .env already exists
if (file_exists('.env')) {
    echo "⚠️  .env file already exists.\n";
    echo "Do you want to backup and create a new one? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) !== 'y') {
        echo "Operation cancelled.\n";
        exit(0);
    }
    
    // Backup existing .env
    $backup_name = '.env.backup.' . date('Y-m-d-H-i-s');
    copy('.env', $backup_name);
    echo "✅ Backed up existing .env to {$backup_name}\n";
}

// Read .env.example as template
$env_example = file_get_contents('.env.example');
if (!$env_example) {
    die("❌ Error: .env.example file not found.\n");
}

echo "🔧 Configuring environment variables...\n\n";

// Environment variables to configure
$env_vars = [
    'APP_NAME' => [
        'prompt' => 'Application name',
        'default' => 'HomyGo',
        'required' => true
    ],
    'APP_ENV' => [
        'prompt' => 'Environment (local/staging/production)',
        'default' => 'production',
        'required' => true
    ],
    'APP_DEBUG' => [
        'prompt' => 'Debug mode (true/false)',
        'default' => 'false',
        'required' => true
    ],
    'APP_URL' => [
        'prompt' => 'Application URL',
        'default' => 'https://your-app-name.onrender.com',
        'required' => true
    ],
    'DB_CONNECTION' => [
        'prompt' => 'Database connection (sqlite/mysql/pgsql)',
        'default' => 'pgsql',
        'required' => true
    ],
    'DB_HOST' => [
        'prompt' => 'Database host',
        'default' => '127.0.0.1',
        'required' => false
    ],
    'DB_PORT' => [
        'prompt' => 'Database port',
        'default' => '5432',
        'required' => false
    ],
    'DB_DATABASE' => [
        'prompt' => 'Database name',
        'default' => 'homygo',
        'required' => false
    ],
    'DB_USERNAME' => [
        'prompt' => 'Database username',
        'default' => 'root',
        'required' => false
    ],
    'DB_PASSWORD' => [
        'prompt' => 'Database password',
        'default' => '',
        'required' => false,
        'hidden' => true
    ],
    'MAIL_MAILER' => [
        'prompt' => 'Mail driver (smtp/sendmail/mailgun/ses)',
        'default' => 'smtp',
        'required' => true
    ],
    'MAIL_HOST' => [
        'prompt' => 'Mail host',
        'default' => 'smtp.gmail.com',
        'required' => false
    ],
    'MAIL_PORT' => [
        'prompt' => 'Mail port',
        'default' => '587',
        'required' => false
    ],
    'MAIL_USERNAME' => [
        'prompt' => 'Mail username',
        'default' => '',
        'required' => false
    ],
    'MAIL_PASSWORD' => [
        'prompt' => 'Mail password',
        'default' => '',
        'required' => false,
        'hidden' => true
    ],
    'MAIL_FROM_ADDRESS' => [
        'prompt' => 'Mail from address',
        'default' => 'hello@homygo.com',
        'required' => true
    ],
];

$env_content = $env_example;
$handle = fopen("php://stdin", "r");

foreach ($env_vars as $key => $config) {
    $prompt = $config['prompt'];
    $default = $config['default'];
    $required = $config['required'];
    $hidden = $config['hidden'] ?? false;
    
    do {
        if ($hidden) {
            echo "{$prompt}" . ($default ? " (default: ***)" : "") . ": ";
            // For password fields, you might want to implement hidden input
            $value = trim(fgets($handle));
        } else {
            echo "{$prompt}" . ($default ? " (default: {$default})" : "") . ": ";
            $value = trim(fgets($handle));
        }
        
        if (empty($value) && !empty($default)) {
            $value = $default;
        }
        
        if ($required && empty($value)) {
            echo "❌ This field is required. Please enter a value.\n";
        }
    } while ($required && empty($value));
    
    // Update environment content
    $pattern = "/^{$key}=.*$/m";
    $replacement = "{$key}={$value}";
    $env_content = preg_replace($pattern, $replacement, $env_content);
}

fclose($handle);

// Generate APP_KEY if not set
if (!preg_match('/^APP_KEY=.+$/m', $env_content)) {
    echo "\n🔑 Generating application key...\n";
    $app_key = 'base64:' . base64_encode(random_bytes(32));
    $env_content = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$app_key}", $env_content);
}

// Set secure session settings for production
if (strpos($env_content, 'APP_ENV=production') !== false) {
    echo "🔒 Applying production security settings...\n";
    
    $security_updates = [
        'SESSION_SECURE' => 'true',
        'SESSION_SAME_SITE' => 'strict',
        'LOG_LEVEL' => 'error',
        'LOG_CHANNEL' => 'stack',
        'CACHE_STORE' => 'redis',
        'QUEUE_CONNECTION' => 'redis',
        'SESSION_DRIVER' => 'redis',
    ];
    
    foreach ($security_updates as $key => $value) {
        $pattern = "/^{$key}=.*$/m";
        if (preg_match($pattern, $env_content)) {
            $env_content = preg_replace($pattern, "{$key}={$value}", $env_content);
        } else {
            $env_content .= "\n{$key}={$value}";
        }
    }
}

// Write .env file
file_put_contents('.env', $env_content);

echo "\n✅ Environment configuration completed!\n";
echo "📁 .env file created successfully.\n\n";

echo "🚀 Next steps:\n";
echo "1. Run: php artisan migrate --seed\n";
echo "2. Run: php artisan config:cache\n";
echo "3. Run: php artisan route:cache\n";
echo "4. Run: php artisan view:cache\n";
echo "5. Run: npm install && npm run build\n\n";

echo "🛡️  Security recommendations:\n";
echo "1. Set up SSL/TLS certificates\n";
echo "2. Configure firewall rules\n";
echo "3. Set up monitoring and alerting\n";
echo "4. Configure automated backups\n";
echo "5. Review and update security settings regularly\n\n";

echo "✨ Your HomyGo application is ready for enterprise deployment!\n";
