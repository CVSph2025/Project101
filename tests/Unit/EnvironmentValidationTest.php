<?php

namespace Tests\Unit;

use App\Services\EnvironmentValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EnvironmentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected EnvironmentValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new EnvironmentValidationService();
    }

    /** @test */
    public function validates_required_environment_variables()
    {
        // Set valid environment
        Config::set('app.name', 'HomyGo');
        Config::set('app.env', 'testing');
        Config::set('app.key', 'base64:' . base64_encode('32-character-secret-key-here!!'));
        Config::set('database.default', 'sqlite');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['is_valid']);
        $this->assertEmpty($validation['missing_variables']);
    }

    /** @test */
    public function detects_missing_app_key()
    {
        Config::set('app.key', null);

        $validation = $this->validationService->validateEnvironment();

        $this->assertFalse($validation['is_valid']);
        $this->assertContains('APP_KEY', $validation['missing_variables']);
    }

    /** @test */
    public function detects_missing_database_configuration()
    {
        Config::set('database.connections.mysql.host', null);
        Config::set('database.connections.mysql.database', null);

        $validation = $this->validationService->validateEnvironment();

        $this->assertFalse($validation['is_valid']);
        $this->assertContains('DB_HOST', $validation['missing_variables']);
        $this->assertContains('DB_DATABASE', $validation['missing_variables']);
    }

    /** @test */
    public function validates_stripe_configuration_when_available()
    {
        Config::set('services.stripe.key', 'pk_test_valid_key');
        Config::set('services.stripe.secret', 'sk_test_valid_secret');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['stripe']['configured']);
        $this->assertTrue($validation['stripe']['valid']);
    }

    /** @test */
    public function handles_missing_stripe_configuration_gracefully()
    {
        Config::set('services.stripe.key', null);
        Config::set('services.stripe.secret', null);

        $validation = $this->validationService->validateEnvironment();

        $this->assertFalse($validation['stripe']['configured']);
        $this->assertFalse($validation['stripe']['valid']);
    }

    /** @test */
    public function validates_mail_configuration()
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.mailtrap.io');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.username', 'test_user');
        Config::set('mail.mailers.smtp.password', 'test_password');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['mail']['configured']);
    }

    /** @test */
    public function detects_invalid_mail_configuration()
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', null);
        Config::set('mail.mailers.smtp.username', null);

        $validation = $this->validationService->validateEnvironment();

        $this->assertFalse($validation['mail']['configured']);
        $this->assertContains('MAIL_HOST', $validation['missing_variables']);
        $this->assertContains('MAIL_USERNAME', $validation['missing_variables']);
    }

    /** @test */
    public function validates_cache_configuration()
    {
        Config::set('cache.default', 'redis');
        Config::set('database.redis.default.host', '127.0.0.1');
        Config::set('database.redis.default.port', 6379);

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['cache']['configured']);
    }

    /** @test */
    public function validates_queue_configuration()
    {
        Config::set('queue.default', 'redis');
        Config::set('database.redis.default.host', '127.0.0.1');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['queue']['configured']);
    }

    /** @test */
    public function calculates_configuration_health_score()
    {
        // Set up a mostly complete configuration
        Config::set('app.key', 'base64:' . base64_encode('32-character-secret-key-here!!'));
        Config::set('app.env', 'production');
        Config::set('database.default', 'mysql');
        Config::set('services.stripe.key', 'pk_live_valid');
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');

        $status = $this->validationService->getConfigurationStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('score', $status);
        $this->assertArrayHasKey('status', $status);
        $this->assertGreaterThan(70, $status['score']); // Should be high with good config
    }

    /** @test */
    public function production_environment_requires_https()
    {
        Config::set('app.env', 'production');
        Config::set('app.url', 'http://example.com'); // Non-HTTPS

        $validation = $this->validationService->validateEnvironment();

        $this->assertContains('APP_URL should use HTTPS in production', $validation['warnings']);
    }

    /** @test */
    public function production_environment_requires_secure_session_settings()
    {
        Config::set('app.env', 'production');
        Config::set('session.secure', false);
        Config::set('session.same_site', 'lax');

        $validation = $this->validationService->validateEnvironment();

        $this->assertContains('SESSION_SECURE should be true in production', $validation['warnings']);
    }

    /** @test */
    public function validates_file_permissions()
    {
        $validation = $this->validationService->validateEnvironment();

        $this->assertArrayHasKey('file_permissions', $validation);
        $this->assertArrayHasKey('storage_writable', $validation['file_permissions']);
        $this->assertArrayHasKey('bootstrap_cache_writable', $validation['file_permissions']);
    }

    /** @test */
    public function validates_php_extensions()
    {
        $validation = $this->validationService->validateEnvironment();

        $this->assertArrayHasKey('php_extensions', $validation);
        $this->assertTrue($validation['php_extensions']['pdo']);
        $this->assertTrue($validation['php_extensions']['openssl']);
        $this->assertTrue($validation['php_extensions']['json']);
    }

    /** @test */
    public function detects_debug_mode_in_production()
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', true);

        $validation = $this->validationService->validateEnvironment();

        $this->assertContains('APP_DEBUG should be false in production', $validation['warnings']);
    }

    /** @test */
    public function validates_timezone_configuration()
    {
        Config::set('app.timezone', 'Asia/Manila');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['timezone']['valid']);
        $this->assertEquals('Asia/Manila', $validation['timezone']['current']);
    }

    /** @test */
    public function detects_invalid_timezone()
    {
        Config::set('app.timezone', 'Invalid/Timezone');

        $validation = $this->validationService->validateEnvironment();

        $this->assertFalse($validation['timezone']['valid']);
    }

    /** @test */
    public function creates_health_check_response()
    {
        $response = $this->validationService->createHealthCheckResponse();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('environment', $response);
        $this->assertArrayHasKey('database', $response);
        $this->assertArrayHasKey('cache', $response);
        $this->assertArrayHasKey('mail', $response);
        $this->assertArrayHasKey('storage', $response);
    }

    /** @test */
    public function health_check_includes_system_information()
    {
        $response = $this->validationService->createHealthCheckResponse();

        $this->assertArrayHasKey('system', $response);
        $this->assertArrayHasKey('php_version', $response['system']);
        $this->assertArrayHasKey('laravel_version', $response['system']);
        $this->assertArrayHasKey('memory_usage', $response['system']);
    }

    /** @test */
    public function validates_social_auth_configuration()
    {
        Config::set('services.google.client_id', 'google_client_id');
        Config::set('services.google.client_secret', 'google_client_secret');
        Config::set('services.facebook.client_id', 'facebook_client_id');
        Config::set('services.facebook.client_secret', 'facebook_client_secret');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['social_auth']['google']['configured']);
        $this->assertTrue($validation['social_auth']['facebook']['configured']);
    }

    /** @test */
    public function detects_missing_social_auth_configuration()
    {
        Config::set('services.google.client_id', null);
        Config::set('services.facebook.client_secret', null);

        $validation = $this->validationService->validateEnvironment();

        $this->assertFalse($validation['social_auth']['google']['configured']);
        $this->assertFalse($validation['social_auth']['facebook']['configured']);
    }

    /** @test */
    public function validates_logging_configuration()
    {
        Config::set('logging.default', 'daily');
        Config::set('logging.channels.daily.path', storage_path('logs/laravel.log'));

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['logging']['configured']);
        $this->assertTrue($validation['logging']['writable']);
    }

    /** @test */
    public function performance_check_measures_response_times()
    {
        $status = $this->validationService->getConfigurationStatus();

        $this->assertArrayHasKey('performance', $status);
        $this->assertArrayHasKey('database_response_time', $status['performance']);
        $this->assertArrayHasKey('cache_response_time', $status['performance']);
    }

    /** @test */
    public function security_check_validates_configuration()
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.url', 'https://example.com');
        Config::set('session.secure', true);
        Config::set('session.same_site', 'strict');

        $validation = $this->validationService->validateEnvironment();

        $this->assertTrue($validation['security']['secure_app_url']);
        $this->assertTrue($validation['security']['secure_sessions']);
        $this->assertTrue($validation['security']['debug_disabled']);
    }

    /** @test */
    public function dependency_check_validates_required_packages()
    {
        $validation = $this->validationService->validateEnvironment();

        $this->assertArrayHasKey('dependencies', $validation);
        $this->assertArrayHasKey('composer_packages', $validation['dependencies']);
        
        // Check for critical packages
        $packages = $validation['dependencies']['composer_packages'];
        $this->assertArrayHasKey('laravel/framework', $packages);
        $this->assertArrayHasKey('spatie/laravel-permission', $packages);
    }

    /** @test */
    public function storage_check_validates_disk_space()
    {
        $validation = $this->validationService->validateEnvironment();

        $this->assertArrayHasKey('storage', $validation);
        $this->assertArrayHasKey('disk_space', $validation['storage']);
        $this->assertArrayHasKey('available_space', $validation['storage']['disk_space']);
        $this->assertArrayHasKey('total_space', $validation['storage']['disk_space']);
    }

    /** @test */
    public function environment_validation_caches_results()
    {
        // First call
        $start = microtime(true);
        $first = $this->validationService->validateEnvironment();
        $firstTime = microtime(true) - $start;

        // Second call should be faster (cached)
        $start = microtime(true);
        $second = $this->validationService->validateEnvironment();
        $secondTime = microtime(true) - $start;

        $this->assertEquals($first['is_valid'], $second['is_valid']);
        // Second call should be significantly faster
        $this->assertLessThan($firstTime, $secondTime);
    }
}
