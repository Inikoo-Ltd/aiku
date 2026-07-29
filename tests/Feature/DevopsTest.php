<?php

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Events\AppVersionWebsocketEvent;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\DevOps\AppDeployment;
use App\Models\DevOps\WebsiteHealthLog;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    // Set dummy webhook URL
    Config::set('services.discord.webhook_url', 'https://discord.com/api/webhooks/1/A');
});

it('logs success and does NOT send notification when website is up', function () {
    Http::fake([
        'https://example.test' => Http::response('OK'),
    ]);

    $this->artisan('monitor:webpage-uptime', ['url' => 'https://example.test'])
        ->assertSuccessful();

    $this->assertDatabaseMissing('website_health_logs', [
        'url'   => 'https://example.test',
        'is_up' => true,
    ]);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'discord.com');
    });
});

it('logs failure and sends alert notification when website returns 500', function () {
    $deployment = AppDeployment::create([
        'commit_hash' => 'dummy123',
    ]);

    Http::fake([
        'https://example.test'                 => Http::response('Error', 500),
        'https://discord.com/api/webhooks/1/A' => Http::response('OK'),
    ]);

    $this->artisan('monitor:webpage-uptime', ['url' => 'https://example.test'])
        ->assertSuccessful();

    $this->assertDatabaseHas('website_health_logs', [
        'url'                  => 'https://example.test',
        'is_up'                => false,
        'status_code'          => 500,
        'error_message'        => 'Received status code: 500',
        'last_deployment_date' => $deployment->created_at->format('Y-m-d H:i:s'),
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/1/A'
            && str_contains($request['content'], 'Website Down Alert')
            && str_contains($request['content'], 'https://example.test')
            && str_contains($request['content'], '500');
    });
});

it('handles timeout and connection exceptions correctly', function () {
    $deployment = AppDeployment::create([
        'commit_hash' => 'dummy123',
    ]);

    Http::fake([
        'https://example.test'                 => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
        'https://discord.com/api/webhooks/1/A' => Http::response('OK'),
    ]);

    $this->artisan('monitor:webpage-uptime', ['url' => 'https://example.test'])
        ->assertSuccessful();

    $this->assertDatabaseHas('website_health_logs', [
        'url'                  => 'https://example.test',
        'is_up'                => false,
        'status_code'          => null,
        'error_message'        => 'Connection timed out',
        'last_deployment_date' => $deployment->created_at->format('Y-m-d H:i:s'),
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/1/A'
            && str_contains($request['content'], 'Website Down Alert')
            && str_contains($request['content'], 'https://example.test')
            && str_contains($request['content'], 'Connection timed out');
    });
});

it('monitors all active and migrated websites', function () {
    /** @noinspection PhpUnhandledExceptionInspection */
    [$organisation, , $shop] = createShop();

    /** @var Website $websiteUp */
    $websiteUp = Website::factory()->create([
        'shop_id'         => $shop->id,
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'type'            => WebsiteTypeEnum::INFO,
        'state'           => WebsiteStateEnum::LIVE,
        'migrated'        => true,
        'status'          => true,
    ]);
    /** @var Webpage $webpageUp */
    $webpageUp = Webpage::factory()->create([
        'website_id'      => $websiteUp->id,
        'shop_id'         => $shop->id,
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'canonical_url'   => 'https://up.test',
        'level'           => 0,
        'state'           => WebpageStateEnum::LIVE,
    ]);
    $websiteUp->update(['storefront_id' => $webpageUp->id]);

    /** @var Website $websiteDown */
    $websiteDown = Website::factory()->create([
        'shop_id'         => $shop->id,
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'type'            => WebsiteTypeEnum::INFO,
        'state'           => WebsiteStateEnum::LIVE,
        'migrated'        => true,
        'status'          => true,
    ]);
    /** @var Webpage $webpageDown */
    $webpageDown = Webpage::factory()->create([
        'website_id'      => $websiteDown->id,
        'shop_id'         => $shop->id,
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'canonical_url'   => 'https://down.test',
        'level'           => 0,
        'state'           => WebpageStateEnum::LIVE,
    ]);
    $websiteDown->update(['storefront_id' => $webpageDown->id]);

    Http::fake([
        'https://up.test'                      => Http::response('OK'),
        'https://down.test'                    => Http::response('Error', 500),
        'https://discord.com/api/webhooks/1/A' => Http::response('OK'),
    ]);

    $this->artisan('monitor:websites')
        ->expectsOutput('Website https://up.test is up')
        ->expectsOutput('Website https://down.test is down')
        ->assertSuccessful();
});

it('prunes old website health logs', function () {
    $oldLog             = WebsiteHealthLog::create([
        'url'           => 'https://old.test',
        'is_up'         => false,
        'error_message' => 'Old error',
    ]);
    $oldLog->created_at = now()->subDays(31);
    $oldLog->save();

    WebsiteHealthLog::create([
        'url'   => 'https://new.test',
        'is_up' => true,
    ]);

    $this->artisan('website-health-logs:prune', ['--days' => 30])
        ->expectsOutput('Pruned 1 old website health logs.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('website_health_logs', ['url' => 'https://old.test']);
    $this->assertDatabaseHas('website_health_logs', ['url' => 'https://new.test']);
});

it('can record a deployment via artisan command', function () {
    $commit = 'abc123456';

    $this->artisan('deploy:record-deployment', ['--commit' => $commit])
        ->expectsOutput("Deployment recorded successfully for commit $commit.")
        ->assertSuccessful();

    $this->assertDatabaseHas('app_deployments', [
        'commit_hash' => $commit,
    ]);
});

it('generates an AI change log and saves committers between deployments', function () {
    $previousHash = trim(exec('git rev-parse HEAD~2'));
    $currentHash  = trim(exec('git rev-parse HEAD'));

    AppDeployment::create(['commit_hash' => $previousHash]);
    $deployment = AppDeployment::create(['commit_hash' => $currentHash]);

    Config::set('askbot-laravel.openai_api_key', 'test-key');
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'We made the app better!']]],
        ]),
        'https://api.github.com/*' => Http::response([
            'author' => ['login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
        ]),
    ]);

    \App\Actions\DevOps\AppDeployment\GenerateAppDeploymentChangeLog::run($deployment);

    $deployment->refresh();
    expect($deployment->change_log)->toBe('We made the app better!')
        ->and($deployment->committers)->not->toBeEmpty()
        ->and($deployment->committers[0]['avatar'])->toBe('https://avatars.githubusercontent.com/u/1');

    $this->assertDatabaseHas('committers', [
        'email'           => $deployment->committers[0]['email'],
        'github_username' => 'octocat',
        'avatar'          => 'https://avatars.githubusercontent.com/u/1',
    ]);
});

it('skips change log generation when commits are not resolvable', function () {
    AppDeployment::create(['commit_hash' => 'previousdummy']);
    $deployment = AppDeployment::create(['commit_hash' => 'notarealhash']);

    Http::fake();

    \App\Actions\DevOps\AppDeployment\GenerateAppDeploymentChangeLog::run($deployment);

    expect($deployment->refresh()->change_log)->toBeNull();
    Http::assertNothingSent();
});

it('records the deployment even when change log generation fails', function () {
    \App\Actions\DevOps\AppDeployment\GenerateAppDeploymentChangeLog::shouldRun()
        ->andThrow(new \RuntimeException('boom'));

    $this->artisan('deploy:record-deployment', ['--commit' => 'resilient123'])
        ->assertSuccessful();

    $this->assertDatabaseHas('app_deployments', [
        'commit_hash' => 'resilient123',
    ]);
});

it('broadcasts the latest deployment info to refresh vue', function () {
    $deployment = AppDeployment::create([
        'commit_hash'      => 'wsdummy123',
        'semantic_version' => 'v9.9.9',
        'change_log'       => 'We made the app better!',
        'committers'       => [['name' => 'Raul Perusquia', 'email' => 'raul@inikoo.com', 'github_username' => null, 'avatar' => null]],
    ]);

    Event::fake([AppVersionWebsocketEvent::class]);

    $this->artisan('deploy:refresh_vue')
        ->expectsOutput('Refresh vue.')
        ->assertSuccessful();

    Event::assertDispatched(AppVersionWebsocketEvent::class, function (AppVersionWebsocketEvent $event) use ($deployment) {
        return $event->appDeployment?->id === $deployment->id
            && $event->broadcastWith()['deployment']['semantic_version'] === 'v9.9.9'
            && $event->broadcastWith()['deployment']['change_log'] === 'We made the app better!';
    });
});

it('can record a deployment without a commit hash', function () {
    $this->artisan('deploy:record-deployment')
        ->expectsOutput('Deployment recorded successfully.')
        ->assertSuccessful();

    $this->assertDatabaseHas('app_deployments', [
        'commit_hash' => null,
    ]);
});
