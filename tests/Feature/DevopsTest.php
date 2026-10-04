<?php

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Events\AppVersionWebsocketEvent;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\DevOps\AppDeployment;
use App\Models\DevOps\WebsiteHealthLog;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    WebsiteHealthLog::create([
        'url'           => 'https://example.test',
        'is_up'         => false,
        'error_message' => 'Earlier failed check',
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

    WebsiteHealthLog::create([
        'url'           => 'https://example.test',
        'is_up'         => false,
        'error_message' => 'Earlier failed check',
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

it('alerts once when nightowl telemetry has stopped arriving', function () {
    Config::set('database.connections.nightowl.host', '127.0.0.1');
    Config::set('database.connections.nightowl.port', 1);
    Config::set('database.connections.nightowl.database', 'unreachable');
    DB::purge('nightowl');
    Cache::forget('monitor:nightowl_ingest:alerted');

    Http::fake([
        'https://discord.com/api/webhooks/1/A' => Http::response('OK'),
    ]);

    $this->artisan('monitor:nightowl_ingest')->assertFailed();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'discord.com')
            && str_contains($request['content'], 'NightOwl Ingest Alert');
    });

    // A stalled agent stays stalled — the throttle keeps it to one alert per window.
    $this->artisan('monitor:nightowl_ingest')->assertFailed();

    Http::assertSentCount(1);

    Cache::forget('monitor:nightowl_ingest:alerted');
});

it('resolves the nightowl agent buffer to an absolute path inside storage', function () {
    // A path outside storage/ sits in the deploy-rewritten anchor tree, where a
    // release unlinks the buffer under the running agent and telemetry silently stops.
    $path = config('nightowl.agent.sqlite_path');

    expect($path)->toStartWith(storage_path().DIRECTORY_SEPARATOR);
});

it('does NOT send notification for a single unconfirmed failure', function () {
    Http::fake([
        'https://isolated.test'                => Http::response('Error', 500),
        'https://discord.com/api/webhooks/1/A' => Http::response('OK'),
    ]);

    $this->artisan('monitor:webpage-uptime', ['url' => 'https://isolated.test'])
        ->assertSuccessful();

    $this->assertDatabaseHas('website_health_logs', [
        'url'   => 'https://isolated.test',
        'is_up' => false,
    ]);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'discord.com');
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

test('every horizon supervisor reserves jobs for longer than any job it runs can take', function () {
    $queueCeilings = [];

    foreach (File::allFiles(app_path('Actions')) as $file) {
        $source = $file->getContents();

        if (!preg_match("/jobQueue\\s*=\\s*'([^']+)'/", $source, $queue)) {
            continue;
        }

        $jobTimeout = preg_match('/jobTimeout\\s*=\\s*(\\d+)/', $source, $timeout) ? (int)$timeout[1] : 0;

        $queueCeilings[$queue[1]] = max($queueCeilings[$queue[1]] ?? 0, $jobTimeout);
    }

    $offenders = [];

    foreach (config('horizon.defaults') as $name => $supervisor) {
        $ceiling = $supervisor['timeout'];

        foreach ((array)$supervisor['queue'] as $queue) {
            $ceiling = max($ceiling, $queueCeilings[$queue] ?? 0);
        }

        $retryAfter = config('queue.connections.'.$supervisor['connection'].'.retry_after');

        if ($retryAfter <= $ceiling) {
            $offenders[$name] = 'retry_after '.$retryAfter.' <= longest possible run '.$ceiling;
        }
    }

    expect($offenders)->toBe([]);
});

it('records each ai call under its feature, rolls it into the ai time series and shows it on the ai dashboard', function () {
    Config::set('services.openrouter.api_key', 'or-key');
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    Cache::forget('ai:openrouter_balance');
    DB::table('ai_usages')->delete();
    Event::fake([App\Events\BroadcastAiUsageChanged::class]);

    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response([
            'model'   => 'openai/gpt-4o-mini',
            'choices' => [['message' => ['content' => 'es']]],
            'usage'   => ['prompt_tokens' => 50, 'completion_tokens' => 1, 'cost' => 0.0001, 'is_byok' => true, 'cost_details' => ['upstream_inference_cost' => 0.002]],
        ]),
        'openrouter.ai/api/v1/key'     => Http::response(['data' => ['limit' => 10, 'limit_remaining' => 9.5, 'limit_reset' => 'monthly', 'usage_daily' => 0.5, 'usage_weekly' => 0.5, 'usage_monthly' => 0.5]]),
        'openrouter.ai/api/v1/credits' => Http::response(['data' => ['total_credits' => 20, 'total_usage' => 2]]),
    ]);

    App\Actions\Helpers\Translations\DetectLanguageWithAI::run('Hola, ¿dónde está mi pedido?');
    App\Actions\Helpers\Translations\DetectLanguageWithAI::run('¿Tienen stock?');

    $usages = DB::table('ai_usages')->get();
    expect($usages)->toHaveCount(2)
        ->and($usages->pluck('feature')->unique()->all())->toBe(['DetectLanguageWithAI'])
        ->and($usages->first()->model)->toBe('openai/gpt-4o-mini')
        ->and((float) $usages->first()->cost)->toBe(0.0021);

    Event::assertDispatched(App\Events\BroadcastAiUsageChanged::class, 2);

    $monthly = App\Models\Helpers\AiTimeSeries::where('feature', 'DetectLanguageWithAI')->where('frequency', 'monthly')->first()->records()->first();
    expect($monthly->number_calls)->toBe(2)
        ->and((float) $monthly->cost)->toBe(0.0042)
        ->and($monthly->prompt_tokens)->toBe(100);

    $this->actingAs(createAdminGuest(createGroup())->getUser())
        ->get(route('grp.ai.dashboard'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Ai/Dashboard')
            ->where('balance.left', 9.5)
            ->where('balance.is_low', false)
            ->where('features.0.feature', 'DetectLanguageWithAI')
            ->where('features.0.label', 'Language detection')
            ->where('features.0.calls_month', 2)
            ->has('daily', 1)
            ->where('models.0.label', 'GPT-4o-mini')
            ->where('models.0.calls', 2));

    $this->get(route('grp.ai.features.show', ['feature' => 'DetectLanguageWithAI']))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Ai/Feature')
            ->where('title', 'Language detection')
            ->where('description', 'Works out the language a customer writes in.')
            ->where('spend.calls_month', 2)
            ->where('models.0.label', 'GPT-4o-mini')
            ->has('calls', 2)
            ->where('calls.0.prompt_tokens', 50));

    $this->get(route('grp.ai.features.show', ['feature' => 'NotAFeature']))->assertNotFound();
});

it('names ai features the way staff talk about them', function () {
    $dashboard = App\Actions\Helpers\AI\UI\ShowAiDashboard::make();

    expect($dashboard->featureLabel('ChatGPT5Driver'))->toBe('Translations')
        ->and($dashboard->featureLabel('DetectLanguageWithAI'))->toBe('Language detection')
        ->and($dashboard->featureLabel('DetectLanguageWithJev'))->toBe('Language detection (Jev)')
        ->and($dashboard->featureLabel('Translate'))->toBe('Translation checks')
        ->and($dashboard->featureLabel('ReadPOFromAIVendorPDF'))->toBe('Read po from ai vendor pdf')
        ->and($dashboard->modelLabel('typesafe/jev-1.13-20260917'))->toBe('Jev 1.13')
        ->and($dashboard->modelLabel('openai/gpt-6-sol'))->toBe('GPT-6-sol');
});

it('alerts discord once when the ai credit left drops below the threshold', function () {
    Config::set('services.openrouter.api_key', 'or-key');
    Config::set('services.openrouter.low_credit_alert', 5);
    Cache::forget('monitor:ai_credit:alerted');

    Http::fake([
        'openrouter.ai/api/v1/key'             => Http::response(['data' => ['limit' => null, 'usage_daily' => 1]]),
        'openrouter.ai/api/v1/credits'         => Http::response(['data' => ['total_credits' => 20, 'total_usage' => 17.5]]),
        'https://discord.com/api/webhooks/1/A' => Http::response('OK'),
    ]);

    $this->artisan('monitor:ai_credit')->assertSuccessful();
    $this->artisan('monitor:ai_credit')->assertSuccessful();

    $alerts = Http::recorded(fn ($request) => str_contains($request->url(), 'discord.com'));
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()[0]['content'])->toContain('$2.50');
});

it('records server usage samples, rolls them into hours and shows them on the devops dashboard', function () {
    Config::set('app.devops_token', 'test-devops-token');

    $sample = [
        'cpu_percent'     => 42.5,
        'memory_percent'  => 61.2,
        'swap_percent'    => 3.1,
        'load_1'          => 2.4,
        'cpu_cores'       => 16,
        'memory_total_mb' => 128000,
        'swap_total_mb'   => 4096,
        'iowait_percent'  => 1.5,
        'net_rx_mbps'     => 10,
        'net_tx_mbps'     => 12,
        'disk_read_mbps'  => 0.5,
        'disk_write_mbps' => 3,
        'processes'       => 900,
        'tcp_connections' => 2300,
        'top_processes'   => [['name' => 'php8.4', 'cpu_percent' => 40.5, 'max_core_percent' => 98, 'processes' => 14], ['name' => 'btop', 'cpu_percent' => 6.2, 'max_core_percent' => 100, 'processes' => 1]],
        'disks'           => [['mount' => '/', 'percent' => 71, 'size_gb' => 900, 'inode_percent' => 7], ['mount' => '/data', 'percent' => 88, 'size_gb' => 3500, 'inode_percent' => 2], ['mount' => '/boot', 'percent' => 95, 'size_gb' => 1, 'inode_percent' => 30]],
    ];

    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), $sample)->assertForbidden();

    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), $sample, ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), [...$sample, 'cpu_percent' => 90], ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), [...$sample, 'cpu_percent' => 140], ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertUnprocessable();

    $server = App\Models\DevOps\Server::where('slug', 'metrics-box')->firstOrFail();
    $first = App\Models\DevOps\ServerMetric::where('server_id', $server->id)->first();
    expect((float) $first->disk_percent)->toBe(88.0)
        ->and((float) $first->inode_percent)->toBe(7.0)
        ->and($first->top_processes[1]['name'])->toBe('btop');

    App\Models\DevOps\ServerMetric::create([...Illuminate\Support\Arr::except($sample, 'disks'), 'server_id' => $server->id, 'disk_percent' => 50, 'recorded_at' => now()->subDays(91)]);

    $this->artisan('server-metrics:aggregate')->assertSuccessful();

    expect(App\Models\DevOps\ServerMetric::where('server_id', $server->id)->count())->toBe(2);
    $hour = DB::table('server_metric_hours')->where('server_id', $server->id)->first();
    expect($hour->samples)->toBe(2)
        ->and((float) $hour->cpu_avg)->toBe(66.25)
        ->and((float) $hour->cpu_max)->toBe(90.0)
        ->and($hour->tcp_connections_max)->toBe(2300)
        ->and((float) $hour->net_rx_avg)->toBe(10.0);

    Event::fake([App\Events\BroadcastServerLiveMetrics::class]);
    Illuminate\Support\Facades\Redis::connection('devops')->del(App\Actions\DevOps\Server\StoreServerLiveMetric::cacheKey('metrics-box'));
    $live = ['cpu_percent' => 12.5, 'iowait_percent' => 0.4, 'memory_percent' => 60, 'net_rx_mbps' => 1.2, 'net_tx_mbps' => 0.8];
    $this->postJson(route('devops.host.metrics.live.store', ['serverSlug' => 'metrics-box']), $live)->assertForbidden();
    $this->postJson(route('devops.host.metrics.live.store', ['serverSlug' => 'metrics-box']), $live, ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    $this->postJson(route('devops.host.metrics.live.store', ['serverSlug' => 'metrics-box']), [...$live, 'cpu_percent' => 30], ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    Event::assertDispatched(App\Events\BroadcastServerLiveMetrics::class, fn ($event) => $event->slug === 'metrics-box' && $event->broadcastWith()['cpu_percent'] === 30.0);
    expect(App\Actions\DevOps\Server\StoreServerLiveMetric::recentReadings('metrics-box'))->toHaveCount(2)
        ->and(App\Models\DevOps\ServerMetric::where('server_id', $server->id)->count())->toBe(2);

    $this->actingAs(createAdminGuest(createGroup())->getUser())
        ->get(route('grp.devops.dashboard'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Devops/Dashboard', false)
            ->where('servers', fn ($servers) => collect($servers)->contains(fn ($row) => $row['slug'] === 'metrics-box' && (float) $row['cpu_percent'] === 90.0 && (float) $row['cpu_24h_max'] === 90.0))
            ->has('liveReadings.metrics-box', 2)
            ->where('servers', fn ($servers) => collect($servers)->firstWhere('slug', 'metrics-box')['swap_total_mb'] === 4096)
            ->where('servers', fn ($servers) => collect($servers)->firstWhere('slug', 'metrics-box')['tcp_connections'] === 2300 && collect($servers)->firstWhere('slug', 'metrics-box')['group'] === 'Other')
            ->where('servers', fn ($servers) => json_decode(collect($servers)->firstWhere('slug', 'metrics-box')['top_processes'], true)[0]['processes'] === 14));

    $this->get(route('grp.docs'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->component('Docs/Dashboard', false)->has('publicSiteVisits.daily')->has('modules'));

    $this->get(route('grp.devops.servers.show', ['server' => 'metrics-box']))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->component('Devops/Server', false)->where('range', '24h')->has('series', 2));

    $this->get(route('grp.devops.servers.show', ['server' => 'metrics-box', 'range' => '30d']))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->where('range', '30d')->has('series', 1)->where('series.0.cpu_max', fn ($value) => (float) $value === 90.0)->where('series.0.processes', 900));
});

it('records github workflow runs, jobs and deploy task progress and shows them on the devops dashboard', function () {
    Config::set('services.github.webhook_secret', 'test-webhook-secret');
    Config::set('app.devops_token', 'test-devops-token');
    Event::fake([App\Events\BroadcastCiRunUpdated::class]);

    $sendWebhook = function (string $event, array $payload, ?string $secret = 'test-webhook-secret') {
        $body = json_encode($payload);

        return $this->call('POST', route('webhooks.github'), [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_ACCEPT'              => 'application/json',
            'HTTP_X_GITHUB_EVENT'      => $event,
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    };

    $run = [
        'id' => 9001, 'name' => 'Deploy Aiku', 'head_branch' => 'production', 'head_sha' => str_repeat('a', 40), 'run_attempt' => 1,
        'head_commit' => ['message' => "📈 Server metrics\n\nmore detail"], 'actor' => ['login' => 'raul'],
        'status' => 'in_progress', 'conclusion' => null, 'html_url' => 'https://github.com/x/y/actions/runs/9001',
        'run_started_at' => now()->subMinutes(2)->toIso8601String(), 'updated_at' => now()->toIso8601String(),
    ];

    $sendWebhook('workflow_run', ['workflow_run' => $run], 'wrong-secret')->assertForbidden();
    $sendWebhook('workflow_run', ['workflow_run' => $run])->assertOk();
    $sendWebhook('workflow_job', ['workflow_job' => [
        'id' => 77, 'run_id' => 9001, 'workflow_name' => 'Deploy Aiku', 'name' => 'Deploy aiku 🚀', 'status' => 'in_progress', 'conclusion' => null,
        'started_at' => now()->subMinutes(2)->toIso8601String(), 'completed_at' => null, 'html_url' => null,
        'steps' => [
            ['number' => 1, 'name' => 'Checkout repo', 'status' => 'completed', 'conclusion' => 'success', 'started_at' => null, 'completed_at' => null],
            ['number' => 2, 'name' => 'Launch 🚀', 'status' => 'in_progress', 'conclusion' => null, 'started_at' => null, 'completed_at' => null],
        ],
    ]])->assertOk();
    $sendWebhook('workflow_job', ['workflow_job' => [
        'id' => 77, 'run_id' => 9001, 'workflow_name' => 'Deploy Aiku', 'name' => 'Deploy aiku 🚀', 'status' => 'in_progress', 'conclusion' => null,
        'started_at' => now()->subMinutes(2)->toIso8601String(), 'completed_at' => null, 'html_url' => null,
        'steps' => [
            ['number' => 1, 'name' => 'Checkout repo', 'status' => 'completed', 'conclusion' => 'success', 'started_at' => null, 'completed_at' => null],
            ['number' => 2, 'name' => 'Launch 🚀', 'status' => 'in_progress', 'conclusion' => null, 'started_at' => null, 'completed_at' => null],
        ],
    ]])->assertOk();
    expect(App\Models\DevOps\CiRun::where('github_run_id', 9001)->first()->jobs)->toHaveCount(1)->toHaveKey('77');
    $sendWebhook('ping', ['zen' => 'hi'])->assertOk();

    $progress = fn (array $data) => $this->postJson(route('devops.deploy-progress.store'), ['run_id' => 9001, 'total' => 30, ...$data], ['X-DEVOPS-TOKEN' => 'test-devops-token']);
    $this->postJson(route('devops.deploy-progress.store'), ['run_id' => 9001, 'task' => 'deploy:migrate', 'state' => 'start'])->assertForbidden();
    $progress(['task' => 'deploy:prepare', 'state' => 'start', 'host' => 'aiku', 'index' => 1])->assertOk();
    $progress(['task' => 'deploy:prepare', 'state' => 'start', 'host' => 'aiku_litio', 'index' => 1])->assertOk();
    $progress(['task' => 'deploy:prepare', 'state' => 'done', 'host' => 'aiku', 'index' => 1])->assertOk();
    $progress(['task' => 'deploy:prepare', 'state' => 'done', 'host' => 'aiku_litio', 'index' => 1])->assertOk();
    $progress(['task' => 'deploy:migrate', 'state' => 'start', 'host' => 'aiku', 'index' => 2])->assertOk();
    $progress(['task' => 'deploy:migrate', 'state' => 'sideways'])->assertUnprocessable();

    Event::assertDispatchedTimes(App\Events\BroadcastCiRunUpdated::class, 8);

    $this->actingAs(createAdminGuest(createGroup())->getUser())
        ->get(route('grp.devops.dashboard'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Devops/Dashboard', false)
            ->where('ciRuns.deploy.github_run_id', 9001)
            ->where('ciRuns.deploy.head_message', '📈 Server metrics')
            ->where('ciRuns.deploy.jobs.0.steps.1.name', 'Launch 🚀')
            ->where('ciRuns.deploy.deploy_total', 30)
            ->where('ciRuns.deploy.deploy_done', 1)
            ->where('ciRuns.deploy.deploy_tasks.0.hosts', ['boro', 'litio'])
            ->where('ciRuns.deploy.deploy_tasks.1.state', 'start')
            ->where('ciRuns.usual_tests_seconds', null));

    $sendWebhook('workflow_run', ['workflow_run' => [...$run, 'status' => 'completed', 'conclusion' => 'failure']])->assertOk();

    $this->get(route('grp.devops.dashboard'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('ciRuns.deploy.conclusion', 'failure')
            ->where('ciRuns.deploy.deploy_tasks.1.state', 'failed'));
});

it('imports recent github workflow runs with their jobs', function () {
    Event::fake([App\Events\BroadcastCiRunUpdated::class]);
    Http::fake([
        'api.github.com/repos/*/actions/runs/9100/jobs*' => Http::response(['jobs' => [[
            'id' => 501, 'run_id' => 9100, 'workflow_name' => 'Backend Tests', 'name' => 'tests', 'status' => 'completed', 'conclusion' => 'failure',
            'started_at' => '2026-10-04T10:00:00Z', 'completed_at' => '2026-10-04T10:12:00Z', 'html_url' => null,
            'steps' => [['number' => 1, 'name' => 'Run tests', 'status' => 'completed', 'conclusion' => 'failure', 'started_at' => null, 'completed_at' => null]],
        ]]]),
        'api.github.com/repos/*/actions/runs*' => Http::response(['workflow_runs' => [[
            'id' => 9100, 'name' => 'Backend Tests', 'head_branch' => 'main', 'head_sha' => str_repeat('b', 40), 'run_attempt' => 1,
            'head_commit' => ['message' => 'Fix things'], 'actor' => ['login' => 'raul'], 'status' => 'completed', 'conclusion' => 'failure',
            'html_url' => null, 'run_started_at' => '2026-10-04T10:00:00Z', 'updated_at' => '2026-10-04T10:12:30Z',
        ]]]),
    ]);

    App\Models\DevOps\CiRun::create(['github_run_id' => 9100, 'jobs' => ['0' => ['name' => 'stale copy', 'steps' => []], '501' => ['name' => 'old', 'steps' => []]]]);

    $this->artisan('ci-runs:import', ['--runs' => 5])->assertSuccessful();

    $ciRun = App\Models\DevOps\CiRun::where('github_run_id', 9100)->firstOrFail();
    expect($ciRun->conclusion)->toBe('failure')
        ->and($ciRun->jobs)->toHaveCount(1)
        ->and($ciRun->jobs['501']['steps'][0]['name'])->toBe('Run tests');
});

it('records backend test results and shows counts, failures and daily stats on the tests tab', function () {
    Config::set('app.devops_token', 'test-devops-token');
    Event::fake([App\Events\BroadcastCiRunUpdated::class]);

    App\Models\DevOps\CiRun::create(['github_run_id' => 9201, 'workflow' => 'Backend Tests', 'branch' => 'main', 'status' => 'completed', 'conclusion' => 'failure', 'started_at' => now()->subMinutes(20), 'completed_at' => now()->subMinutes(8)]);
    App\Models\DevOps\CiRun::create(['github_run_id' => 9200, 'workflow' => 'Backend Tests', 'branch' => 'main', 'status' => 'completed', 'conclusion' => 'success', 'started_at' => now()->subHours(3), 'completed_at' => now()->subHours(3)->addMinutes(10), 'test_results' => ['tests' => 5000, 'failures' => 0, 'errors' => 0, 'skipped' => 4]]);

    $results = ['run_id' => 9201, 'tests' => 5003, 'assertions' => 20000, 'failures' => 2, 'errors' => 1, 'skipped' => 4, 'seconds' => 640.5, 'failed' => [['test' => 'Tests\Feature\OrderingTest › it submits', 'message' => 'Failed asserting that false is true.']]];
    $this->postJson(route('devops.test-results.store'), $results)->assertForbidden();
    $this->postJson(route('devops.test-results.store'), $results, ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    Event::assertDispatched(App\Events\BroadcastCiRunUpdated::class);

    $this->actingAs(createAdminGuest(createGroup())->getUser())
        ->get(route('grp.devops.dashboard'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Devops/Dashboard', false)
            ->where('ciRuns.tests.github_run_id', 9201)
            ->where('ciRuns.tests.test_counts', ['tests' => 5003, 'failed' => 3, 'skipped' => 4])
            ->where('ciRuns.tests.failed_tests.0.test', 'Tests\Feature\OrderingTest › it submits')
            ->where('ciRuns.recent_tests.0.test_counts.tests', 5000)
            ->where('ciRuns.test_stats.runs', fn (int $runs) => $runs >= 2)
            ->where('ciRuns.test_stats.pass_rate', fn ($rate) => $rate > 0 && $rate < 100)
            ->where('ciRuns.test_stats.average_seconds', 600)
            ->where('ciRuns.test_stats.average_tests', 5001)
            ->has('ciRuns.test_stats.daily', 14));
});

it('shows nightowl telemetry graphs, top tables, request, job and command waterfalls, exception stack traces and logs on the devops dashboard', function () {
    $nightowl = DB::connection('nightowl');
    $nightowl->beginTransaction();

    $histogram = collect(NightOwl\Support\QueryHistogram::columns())->map(fn (string $column) => "$column bigint default 0")->implode(', ');
    $counters  = collect(['call_count', 'success_count', 'client_error_count', 'server_error_count', 'processed_count', 'failed_count', 'released_count', 'unsuccessful_count', 'skipped_count', 'handled_count', 'unhandled_count', 'authenticated_count', 'hits', 'misses', 'writes', 'fails', 'total_duration', 'min_duration', 'max_duration'])
        ->map(fn (string $column) => "$column bigint default 0")->implode(', ');
    foreach (['request', 'job', 'command', 'scheduled_task', 'query', 'outgoing_request', 'exception', 'cache'] as $type) {
        $nightowl->statement("create temporary table nightowl_{$type}_hourly_rollups (group_hash varchar, fingerprint varchar, bucket_start timestamp, environment varchar, route_methods text, route_path text, job_class text, queue text, command text, expression text, sql_query text, connection varchar, host text, $counters, $histogram)");
    }
    $nightowl->statement('create temporary table nightowl_issues (id bigint, group_hash varchar, assigned_to varchar, type varchar, status varchar, priority varchar, exception_class varchar, exception_message text, first_seen_at timestamp, last_seen_at timestamp, occurrences_count int, users_count int)');
    $nightowl->statement('create temporary table nightowl_dict_route (id bigint, method varchar, path varchar, name varchar)');
    $nightowl->statement('create temporary table nightowl_dict_sql (id bigint, sql text, file varchar, line int)');
    $nightowl->statement('create temporary table nightowl_dict_string (id int, kind varchar, value varchar)');
    $nightowl->statement('create temporary table nightowl_requests_v2 (id bigint, created_at timestamp, ts_us bigint, trace_id uuid, url text, status_code int, duration bigint, user_id varchar, route_id bigint, queries int, cache_events int, outgoing_requests int, bootstrap bigint, before_middleware bigint, action bigint, render bigint, after_middleware bigint, sending bigint, terminating bigint, peak_memory_usage bigint, exception_preview text)');
    $nightowl->statement('create temporary table nightowl_jobs_v2 (id bigint, created_at timestamp, ts_us bigint, trace_id uuid, attempt_id uuid, attempt int, job_class_id int, queue_id int, status_id int, duration bigint, user_id varchar, queries int, cache_events int, outgoing_requests int, peak_memory_usage bigint, exception_preview text)');
    $nightowl->statement('create temporary table nightowl_commands_v2 (id bigint, created_at timestamp, ts_us bigint, trace_id uuid, name varchar, command text, exit_code int, duration bigint, user_id varchar, bootstrap bigint, action bigint, terminating bigint, queries int, cache_events int, outgoing_requests int, peak_memory_usage bigint, exception_preview text)');
    $spanColumns = 'created_at timestamp, ts_us bigint, trace_id uuid, execution_id uuid, duration bigint';
    $nightowl->statement("create temporary table nightowl_queries_v2 ($spanColumns, sql_id bigint)");
    $nightowl->statement("create temporary table nightowl_cache_events_v2 ($spanColumns, event_type_id int, key varchar)");
    $nightowl->statement("create temporary table nightowl_outgoing_requests_v2 ($spanColumns, method_id int, url text, status_code int)");
    $nightowl->statement("create temporary table nightowl_exceptions_v2 (id bigint, $spanColumns, fingerprint bytea, server_id int, execution_source_id int, execution_stage_id int, trace_ref bigint, handled boolean, user_id varchar, execution_preview varchar, class varchar, message text, code varchar, file varchar, line int, php_version varchar, laravel_version varchar)");
    $nightowl->statement("create temporary table nightowl_logs_v2 (id bigint, $spanColumns, server_id int, execution_source_id int, execution_preview varchar, user_id varchar, level_id int, channel_id int, message text, context_z bytea)");
    $nightowl->statement('create temporary table nightowl_dict_trace (id bigint, trace_z bytea)');
    $nightowl->statement('create temporary table nightowl_exception_server_hourly_rollups (fingerprint varchar, server varchar, bucket_start timestamp, environment varchar, call_count bigint)');

    $hour = now()->startOfHour()->subHour()->toDateTimeString();
    $nightowl->table('nightowl_request_hourly_rollups')->insert([
        ['group_hash' => 'a', 'bucket_start' => $hour, 'route_methods' => '["GET","HEAD"]', 'route_path' => '/products', 'call_count' => 100, 'success_count' => 95, 'client_error_count' => 4, 'server_error_count' => 1, 'total_duration' => 20_000_000, 'max_duration' => 900_000, 'hist_20' => 95, 'hist_26' => 5],
        ['group_hash' => 'b', 'bucket_start' => $hour, 'route_methods' => '["POST"]', 'route_path' => '/basket', 'call_count' => 10, 'success_count' => 10, 'client_error_count' => 0, 'server_error_count' => 0, 'total_duration' => 1_000_000, 'max_duration' => 200_000, 'hist_20' => 10, 'hist_26' => 0],
    ]);
    $nightowl->table('nightowl_job_hourly_rollups')->insert(['group_hash' => 'j', 'bucket_start' => $hour, 'job_class' => 'App\\Jobs\\Hydrate', 'queue' => 'default', 'call_count' => 7, 'processed_count' => 6, 'failed_count' => 1, 'total_duration' => 7_000_000, 'max_duration' => 2_000_000, 'hist_28' => 7]);
    $nightowl->table('nightowl_query_hourly_rollups')->insert(['group_hash' => 'q', 'bucket_start' => $hour, 'sql_query' => 'select * from "products" where "id" = ?', 'connection' => 'pgsql', 'call_count' => 500, 'total_duration' => 5_000_000, 'max_duration' => 80_000, 'hist_10' => 500]);
    $fingerprint = 'fd7619e4eb885b9de0d7e97b4afaebd0';
    $nightowl->table('nightowl_exception_hourly_rollups')->insert(['fingerprint' => $fingerprint, 'bucket_start' => $hour, 'handled_count' => 2, 'unhandled_count' => 3]);
    $nightowl->table('nightowl_exception_server_hourly_rollups')->insert(['fingerprint' => $fingerprint, 'server' => 'litio', 'bucket_start' => $hour, 'call_count' => 5]);
    $nightowl->table('nightowl_cache_hourly_rollups')->insert(['group_hash' => 'c', 'bucket_start' => $hour, 'hits' => 90, 'misses' => 10]);
    $nightowl->table('nightowl_issues')->insert(['id' => 1, 'group_hash' => $fingerprint, 'type' => 'exception', 'status' => 'open', 'exception_class' => 'RuntimeException', 'exception_message' => 'Boom', 'first_seen_at' => $hour, 'last_seen_at' => $hour, 'occurrences_count' => 3, 'users_count' => 1]);

    $traceId   = '210d444d-72fa-466d-a601-1421cd1e2c6c';
    $createdAt = now()->subMinutes(5)->startOfSecond();
    $startUs   = $createdAt->getTimestamp() * 1_000_000;
    $nightowl->table('nightowl_dict_route')->insert(['id' => 68, 'method' => 'GET', 'path' => '/products', 'name' => 'products.index']);
    $nightowl->table('nightowl_dict_sql')->insert(['id' => 9, 'sql' => 'select * from "products"', 'file' => 'app/Actions/ShowProducts.php', 'line' => 30]);
    $nightowl->table('nightowl_dict_string')->insert([
        ['id' => 101, 'kind' => 'event_type', 'value' => 'hit'],
        ['id' => 805, 'kind' => 'job_class', 'value' => 'App\\Jobs\\Hydrate'],
        ['id' => 33, 'kind' => 'queue', 'value' => 'default'],
        ['id' => 48, 'kind' => 'status', 'value' => 'failed'],
        ['id' => 2, 'kind' => 'server', 'value' => 'litio'],
        ['id' => 7, 'kind' => 'execution_source', 'value' => 'request'],
        ['id' => 60, 'kind' => 'level', 'value' => 'warning'],
        ['id' => 61, 'kind' => 'level', 'value' => 'error'],
        ['id' => 1195, 'kind' => 'channel', 'value' => 'production'],
    ]);
    $nightowl->insert("insert into nightowl_logs_v2 (id, created_at, ts_us, trace_id, execution_id, duration, server_id, execution_source_id, level_id, channel_id, message, context_z) values (1, ?, ?, null, ?, 0, 2, 7, 60, 1195, 'Unverified Shopify webhook allowed', decode(?, 'hex')), (2, ?, ?, null, null, 0, 2, null, 61, 1195, 'No route to host', null)", [
        $createdAt, $startUs + 30_000, $traceId, bin2hex(gzdeflate(json_encode(['reason' => 'signature']), 6)), $createdAt->copy()->addSecond(), $startUs + 1_000_000,
    ]);
    $frames = [
        ['file' => 'app/Actions/ShowProducts.php:31', 'source' => '', 'code' => ['30' => '$products = load();', '31' => 'throw new RuntimeException("Boom");']],
        ['file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:170', 'source' => 'App\\Actions\\ShowProducts->handle()', 'code' => null],
    ];
    $nightowl->insert("insert into nightowl_dict_trace (id, trace_z) values (1, decode(?, 'hex'))", [bin2hex(gzdeflate(json_encode($frames), 6))]);
    $nightowl->insert("insert into nightowl_exceptions_v2 (id, created_at, ts_us, trace_id, execution_id, duration, fingerprint, server_id, execution_source_id, trace_ref, handled, class, message, file, line, php_version, laravel_version) values (99, ?, ?, null, ?, 0, decode(?, 'hex'), 2, 7, 1, false, 'RuntimeException', 'Boom', 'app/Actions/ShowProducts.php', 31, '8.4.21', '13.26.1')", [$createdAt, $startUs + 250_000, $traceId, $fingerprint]);
    $attemptId = '2667426f-2004-40c1-b4a4-125243439e26';
    $commandTraceId = 'f3d4b85a-5166-46f4-88ba-088c68aa8e83';
    $nightowl->table('nightowl_jobs_v2')->insert(['id' => 777, 'created_at' => $createdAt, 'ts_us' => $startUs, 'trace_id' => $traceId, 'attempt_id' => $attemptId, 'attempt' => 2, 'job_class_id' => 805, 'queue_id' => 33, 'status_id' => 48, 'duration' => 2_000_000, 'queries' => 1, 'cache_events' => 0, 'outgoing_requests' => 1, 'peak_memory_usage' => 80_000_000, 'exception_preview' => 'RuntimeException: Boom']);
    $nightowl->table('nightowl_commands_v2')->insert(['id' => 888, 'created_at' => $createdAt, 'ts_us' => $startUs, 'trace_id' => $commandTraceId, 'name' => 'data_feeds:save', 'command' => 'data_feeds:save --all', 'exit_code' => 1, 'duration' => 9_000_000, 'bootstrap' => 100_000, 'action' => 8_800_000, 'terminating' => 100_000, 'queries' => 1, 'cache_events' => 0, 'outgoing_requests' => 0, 'peak_memory_usage' => 90_000_000]);
    $nightowl->table('nightowl_queries_v2')->insert([
        ['created_at' => $createdAt, 'ts_us' => $startUs + 1_500_000, 'trace_id' => $traceId, 'execution_id' => $attemptId, 'duration' => 3_000, 'sql_id' => 9],
        ['created_at' => $createdAt->copy()->addSeconds(5), 'ts_us' => $startUs + 5_000_000, 'trace_id' => null, 'execution_id' => $commandTraceId, 'duration' => 4_000, 'sql_id' => 9],
    ]);
    $nightowl->table('nightowl_outgoing_requests_v2')->insert(['created_at' => $createdAt, 'ts_us' => $startUs + 100_000, 'trace_id' => $traceId, 'execution_id' => $attemptId, 'duration' => 900_000, 'method_id' => null, 'url' => 'https://api.shopify.test/products', 'status_code' => 429]);
    $nightowl->table('nightowl_requests_v2')->insert(['id' => 555, 'created_at' => $createdAt, 'ts_us' => $startUs, 'trace_id' => $traceId, 'url' => 'https://aiku.test/products', 'status_code' => 200, 'duration' => 300_000, 'route_id' => 68, 'queries' => 1, 'cache_events' => 1, 'outgoing_requests' => 0, 'bootstrap' => 0, 'before_middleware' => 10_000, 'action' => 280_000, 'render' => 5_000, 'after_middleware' => 2_000, 'sending' => 1_000, 'terminating' => 2_000, 'peak_memory_usage' => 50_000_000]);
    $nightowl->table('nightowl_queries_v2')->insert(['created_at' => $createdAt, 'ts_us' => $startUs + 20_000, 'trace_id' => null, 'execution_id' => $traceId, 'duration' => 1_500, 'sql_id' => 9]);
    $nightowl->table('nightowl_cache_events_v2')->insert(['created_at' => $createdAt, 'ts_us' => $startUs + 5_000, 'trace_id' => null, 'execution_id' => $traceId, 'duration' => 70, 'event_type_id' => 101, 'key' => 'website-1']);

    Cache::forget('devops-telemetry-24h');
    Config::set('inertia.testing.ensure_pages_exist', false);

    try {
        $this->actingAs(createAdminGuest(createGroup())->getUser())
            ->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'range' => '24h', 'trace' => 'request', 'id' => 555, 'at' => $createdAt->toDateTimeString()]))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->component('Devops/Dashboard', false)
                ->missing('telemetry')
                ->missing('telemetryTrace')
                ->reloadOnly('telemetry', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->where('telemetry.range', '24h')
                    ->where('telemetry.requests.0.calls', 110)
                    ->where('telemetry.requests.0.server_errors', fn ($value) => (int) $value === 1)
                    ->where('telemetry.requests.0.p95', fn ($value) => $value > 92_682 && $value <= 131_072)
                    ->where('telemetry.routes.0.label', '["GET","HEAD"] /products')
                    ->where('telemetry.routes.0.avg', 200_000)
                    ->where('telemetry.jobs.0.failed', fn ($value) => (int) $value === 1)
                    ->where('telemetry.jobClasses.0.label', 'App\\Jobs\\Hydrate')
                    ->where('telemetry.queries.0.label', 'select * from "products" where "id" = ?')
                    ->where('telemetry.queries.0.calls', 500)
                    ->where('telemetry.exceptions.0.unhandled', fn ($value) => (int) $value === 3)
                    ->where('telemetry.cache.hits', fn ($value) => (int) $value === 90)
                    ->where('telemetry.exceptionGroups.0.exception_class', 'RuntimeException')
                    ->where('telemetry.exceptionGroups.0.unhandled', fn ($value) => (int) $value === 3)
                    ->where('telemetry.slowRequests.0.route_name', 'products.index')
                    ->where('telemetry.slowJobs.0.job_class', 'App\\Jobs\\Hydrate')
                    ->where('telemetry.slowCommands.0.name', 'data_feeds:save'))
                ->reloadOnly('telemetryTrace', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->where('telemetryTrace.summary.kind', 'request')
                    ->where('telemetryTrace.summary.subtitle', 'products.index')
                    ->where('telemetryTrace.stages.1.label', 'Controller')
                    ->has('telemetryTrace.spans', 4)
                    ->where('telemetryTrace.spans.2.type', 'log')
                    ->where('telemetryTrace.spans.2.label', 'WARNING: Unverified Shopify webhook allowed')
                    ->where('telemetryTrace.spans.3.type', 'exception')
                    ->where('telemetryTrace.spans.0.type', 'cache')
                    ->where('telemetryTrace.spans.0.label', 'hit website-1')
                    ->where('telemetryTrace.spans.1.type', 'query')
                    ->where('telemetryTrace.spans.1.offset', 20_000)
                    ->where('telemetryTrace.spans.1.file', 'app/Actions/ShowProducts.php')));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'trace' => 'job', 'id' => 777, 'at' => $createdAt->toDateTimeString()]))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryTrace', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->where('telemetryTrace.summary.title', 'App\\Jobs\\Hydrate')
                    ->where('telemetryTrace.summary.failed', true)
                    ->has('telemetryTrace.spans', 2)
                    ->where('telemetryTrace.spans.0.type', 'http')
                    ->where('telemetryTrace.spans.0.label', ' https://api.shopify.test/products 429')
                    ->where('telemetryTrace.spans.1.offset', 1_500_000)));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'trace' => 'command', 'id' => 888, 'at' => $createdAt->toDateTimeString()]))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryTrace', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->where('telemetryTrace.summary.title', 'data_feeds:save --all')
                    ->where('telemetryTrace.summary.failed', true)
                    ->has('telemetryTrace.stages', 3)
                    ->has('telemetryTrace.spans', 1)
                    ->where('telemetryTrace.spans.0.offset', 5_000_000)));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'exception' => $fingerprint]))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryException', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->where('telemetryException.issue.status', 'open')
                    ->where('telemetryException.occurrence.class', 'RuntimeException')
                    ->where('telemetryException.occurrence.server', 'litio')
                    ->where('telemetryException.occurrence.frames.0.code.31', 'throw new RuntimeException("Boom");')
                    ->where('telemetryException.occurrence.frames.0.is_vendor', false)
                    ->where('telemetryException.occurrence.frames.1.is_vendor', true)
                    ->where('telemetryException.occurrence.execution', ['kind' => 'request', 'id' => 555, 'at' => $createdAt->toDateTimeString()])
                    ->has('telemetryException.occurrences', 1)
                    ->where('telemetryException.servers.0.server', 'litio')
                    ->where('telemetryException.hourly.0.unhandled', fn ($value) => (int) $value === 3)));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'range' => '24h']))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryLogs', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->has('telemetryLogs.entries', 2)
                    ->where('telemetryLogs.entries.0.level', 'error')
                    ->where('telemetryLogs.entries.1.channel', 'production')
                    ->where('telemetryLogs.entries.1.context', "{\n    \"reason\": \"signature\"\n}")
                    ->where('telemetryLogs.series', fn ($series) => collect($series)->sum('entries') === 2)));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'range' => '24h', 'level' => 'warning', 'search' => 'shopify']))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryLogs', fn (Inertia\Testing\AssertableInertia $reload) => $reload
                    ->where('telemetryLogs.level', 'warning')
                    ->has('telemetryLogs.entries', 1)
                    ->where('telemetryLogs.entries.0.message', 'Unverified Shopify webhook allowed')));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'trace' => 'request', 'execution' => $traceId, 'at' => $createdAt->toDateTimeString()]))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryTrace', fn (Inertia\Testing\AssertableInertia $reload) => $reload->where('telemetryTrace.summary.subtitle', 'products.index')));

        $this->get(route('grp.devops.dashboard', ['tab' => 'telemetry', 'exception' => 'not-a-fingerprint']))
            ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
                ->reloadOnly('telemetryException', fn (Inertia\Testing\AssertableInertia $reload) => $reload->where('telemetryException', null)));
    } finally {
        $nightowl->rollBack();
        Cache::forget('devops-telemetry-24h');
    }
});
