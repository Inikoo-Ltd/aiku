<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    // Set dummy webhook URL
    Config::set('services.discord.webhook_url', 'https://discord.com/api/webhooks/1520964578178240603/EP-CtVWYJTluFtU9tfTKjpvidfwSL9pMtPOaSfvqzfXljYKxVXT17Dz5rCM6OffzF-g-');

    \App\Models\Web\WebsiteHealthLog::truncate();

    $this->deployment = \App\Models\DevOps\AppDeployment::create([
        'commit_hash' => 'dummy123',
    ]);
});

it('logs success and does NOT send notification when website is up', function () {
    Http::fake([
        'https://example.test' => Http::response('OK', 200),
    ]);

    $this->artisan('monitor:homepage-uptime', ['--url' => 'https://example.test'])
        ->assertSuccessful();

    $this->assertDatabaseMissing('website_health_logs', [
        'url' => 'https://example.test',
        'is_up' => true,
    ]);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'discord.com');
    });
});

it('logs failure and sends alert notification when website returns 500', function () {
    Http::fake([
        'https://example.test' => Http::response('Error', 500),
        'https://discord.com/api/webhooks/1520964578178240603/EP-CtVWYJTluFtU9tfTKjpvidfwSL9pMtPOaSfvqzfXljYKxVXT17Dz5rCM6OffzF-g-' => Http::response('OK', 200),
    ]);

    $this->artisan('monitor:homepage-uptime', ['--url' => 'https://example.test'])
        ->assertSuccessful();

    $this->assertDatabaseHas('website_health_logs', [
        'url' => 'https://example.test',
        'is_up' => false,
        'status_code' => 500,
        'error_message' => 'Received status code: 500',
        'last_deployment_date' => $this->deployment->created_at->format('Y-m-d H:i:s'),
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/1520964578178240603/EP-CtVWYJTluFtU9tfTKjpvidfwSL9pMtPOaSfvqzfXljYKxVXT17Dz5rCM6OffzF-g-' &&
            str_contains($request['content'], 'Website Down Alert') &&
            str_contains($request['content'], 'https://example.test') &&
            str_contains($request['content'], '500');
    });
});

it('handles timeout and connection exceptions correctly', function () {
    Http::fake([
        'https://example.test' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
        'https://discord.com/api/webhooks/1520964578178240603/EP-CtVWYJTluFtU9tfTKjpvidfwSL9pMtPOaSfvqzfXljYKxVXT17Dz5rCM6OffzF-g-' => Http::response('OK', 200),
    ]);

    $this->artisan('monitor:homepage-uptime', ['--url' => 'https://example.test'])
        ->assertSuccessful();

    $this->assertDatabaseHas('website_health_logs', [
        'url' => 'https://example.test',
        'is_up' => false,
        'status_code' => null,
        'error_message' => 'Connection timed out',
        'last_deployment_date' => $this->deployment->created_at->format('Y-m-d H:i:s'),
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/1520964578178240603/EP-CtVWYJTluFtU9tfTKjpvidfwSL9pMtPOaSfvqzfXljYKxVXT17Dz5rCM6OffzF-g-' &&
            str_contains($request['content'], 'Website Down Alert') &&
            str_contains($request['content'], 'https://example.test') &&
            str_contains($request['content'], 'Connection timed out');
    });
});
