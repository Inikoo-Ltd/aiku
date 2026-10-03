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
        'disks'           => [['mount' => '/', 'percent' => 71, 'size_gb' => 900, 'inode_percent' => 7], ['mount' => '/data', 'percent' => 88, 'size_gb' => 3500, 'inode_percent' => 2]],
    ];

    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), $sample)->assertForbidden();

    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), $sample, ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), [...$sample, 'cpu_percent' => 90], ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertOk();
    $this->postJson(route('devops.host.metrics.store', ['serverSlug' => 'metrics-box']), [...$sample, 'cpu_percent' => 140], ['X-DEVOPS-TOKEN' => 'test-devops-token'])->assertUnprocessable();

    $server = App\Models\DevOps\Server::where('slug', 'metrics-box')->firstOrFail();
    $first = App\Models\DevOps\ServerMetric::where('server_id', $server->id)->first();
    expect((float) $first->disk_percent)->toBe(88.0)
        ->and((float) $first->inode_percent)->toBe(7.0);

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
            ->where('servers', fn ($servers) => collect($servers)->firstWhere('slug', 'metrics-box')['swap_total_mb'] === 4096));

    $this->get(route('grp.docs'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->component('Docs/Dashboard', false)->has('publicSiteVisits.daily')->has('modules'));

    $this->get(route('grp.devops.servers.show', ['server' => 'metrics-box']))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->component('Devops/Server', false)->where('range', '24h')->has('series', 2));

    $this->get(route('grp.devops.servers.show', ['server' => 'metrics-box', 'range' => '30d']))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->where('range', '30d')->has('series', 1)->where('series.0.cpu_max', fn ($value) => (float) $value === 90.0)->where('series.0.processes', 900));
});
