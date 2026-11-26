<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\UI\ShowSlackSyncHealth;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackSyncRun;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        list($this->organisation, $this->user) = createShop();
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
    }
    $this->group = $this->organisation->group;
    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    Cache::forget(ShowSlackSyncHealth::STATS_CACHE_KEY);

    $this->makeUser = function (?string $role): User {
        $user = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    };

    $this->recordHealthyRuns = function (): void {
        foreach (array_keys(ShowSlackSyncHealth::NIGHTLY_COMMANDS) as $command) {
            SlackSyncRun::create(['command' => $command, 'started_at' => now()->subHours(2), 'finished_at' => now()->subHours(2)->addMinute(), 'totals' => ['done' => 1]]);
        }
    };
});

test('engineers see the Slack sync health page with figures, nightly runs and the channel table', function () {
    ($this->recordHealthyRuns)();
    $prefix  = 'health-'.Str::lower(Str::random(6));
    $channel = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => 'CHEALTH'.Str::upper(Str::random(6)), 'name' => $prefix.'-a', 'is_member' => true, 'history_synced_at' => now()->subHours(3)]);
    SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => 'CHEALTH'.Str::upper(Str::random(6)), 'name' => $prefix.'-b', 'is_member' => false]);
    foreach ([now()->subDays(40), now()->subMinutes(5)] as $index => $sentAt) {
        SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => $sentAt->timestamp.'.00000'.$index, 'text' => 'hello', 'sent_at' => $sentAt]);
    }

    actingAs(($this->makeUser)('help-desk-clerk'))
        ->get(route('grp.devops.slack_sync', ['filter' => ['global' => $prefix], 'sort' => '-newest']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Chat/SlackSyncHealth')
            ->where('summary.channels_kept', fn ($kept) => $kept >= 1)
            ->where('summary.messages', fn ($messages) => $messages >= 2)
            ->where('summary.messages_last_day', fn ($count) => $count >= 1)
            ->has('runs', count(ShowSlackSyncHealth::NIGHTLY_COMMANDS))
            ->where('runs.0.status', 'ok')
            ->where('warnings', fn ($warnings) => collect($warnings)->whereIn('type', ['quiet', 'run_failed', 'run_stuck', 'run_late', 'run_not_recorded'])->isEmpty())
            ->where('summary.channels_not_joined', fn ($notJoined) => $notJoined >= 1)
            ->has('data.data', 2)
            ->where('data.data.1.status', 'not_joined')
            ->where('data.data.0.name', $channel->name)
            ->where('data.data.0.status', 'kept')
            ->where('data.data.0.messages', 2)
            ->where('data.data.0.history_complete', true));
});

test('engineers see the public Slack channels without a Slack account, other staff without one do not', function () {
    $channel  = \App\Models\Chat\SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => 'CENGSEE'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)), 'name' => 'for-engineers']);
    $ignored  = \App\Models\Chat\SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => 'CENGIGN'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)), 'is_ignored' => true]);
    $engineer = ($this->makeUser)('help-desk-clerk');
    $staff    = ($this->makeUser)(null);

    expect($channel->isVisibleTo($engineer))->toBeTrue()
        ->and($ignored->isVisibleTo($engineer))->toBeFalse()
        ->and($channel->isVisibleTo($staff))->toBeFalse();

    $engineer->update(['status' => false]);
    expect($channel->isVisibleTo($engineer->refresh()))->toBeFalse();
});

test('the Slack sync health page is for engineers only', function () {
    actingAs(($this->makeUser)('group-admin'))->get(route('grp.devops.slack_sync'))->assertForbidden();
    actingAs(($this->makeUser)(null))->get(route('grp.devops.slack_sync'))->assertForbidden();
});

test('a failed, unfinished or missing nightly run each raise a warning', function () {
    ($this->recordHealthyRuns)();
    SlackSyncRun::create(['command' => 'slack:sync-public-channels', 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3), 'error' => 'missing_scope']);
    SlackSyncRun::create(['command' => 'slack:sync-channel-members', 'started_at' => now()->subHours(ShowSlackSyncHealth::STUCK_AFTER_HOURS + 1)]);
    SlackSyncRun::create(['command' => 'slack:sync-emojis', 'started_at' => now()->subMinutes(2)]);
    SlackSyncRun::create(['command' => 'slack:sync-users', 'started_at' => now()->subHours(ShowSlackSyncHealth::LATE_AFTER_HOURS + 2), 'finished_at' => now()->subHours(ShowSlackSyncHealth::LATE_AFTER_HOURS + 2)]);

    $action = ShowSlackSyncHealth::make();
    $runs   = collect($action->nightlyRuns())->pluck('status', 'command');

    expect($runs->all())->toBe([
        'slack:sync-public-channels'    => 'failed',
        'slack:sync-users'              => 'late',
        'slack:sync-channel-members'    => 'stuck',
        'slack:sync-pins-and-bookmarks' => 'ok',
        'slack:sync-emojis'             => 'running',
        'slack:sync-private-channels'   => 'ok',
    ]);

    $warnings = collect($action->warnings(['newest_message_at' => now()->toIso8601String(), 'files_failed' => 0], $action->nightlyRuns()));
    expect($warnings->pluck('type', 'command')->filter()->all())->toBe([
        'slack:sync-public-channels' => 'run_failed',
        'slack:sync-users'           => 'run_late',
        'slack:sync-channel-members' => 'run_stuck',
    ])->and($warnings->firstWhere('type', 'run_failed')['error'])->toBe('missing_scope');
});

test('files in the failed state raise a warning', function () {
    ($this->recordHealthyRuns)();
    SlackFile::create(['group_id' => $this->group->id, 'slack_id' => 'FHEALTH'.Str::upper(Str::random(8)), 'name' => 'broken.png', 'state' => 'failed', 'size' => 10]);

    $action  = ShowSlackSyncHealth::make();
    $summary = $action->summary($this->group);

    expect($summary['files_failed'])->toBeGreaterThanOrEqual(1)
        ->and(collect($action->warnings($summary, $action->nightlyRuns()))->firstWhere('type', 'files_failed')['count'])->toBe($summary['files_failed']);
});

test('no message stored for three working hours raises a warning, while nights and weekends do not count', function () {
    $friday = Carbon::parse('2026-11-20 14:30:00', 'UTC');

    expect(ShowSlackSyncHealth::workingMinutesBetween($friday, Carbon::parse('2026-11-23 06:00:00', 'UTC')))->toBe(90)
        ->and(ShowSlackSyncHealth::workingMinutesBetween($friday, Carbon::parse('2026-11-23 09:00:00', 'UTC')))->toBe(210)
        ->and(ShowSlackSyncHealth::workingMinutesBetween(Carbon::parse('2026-11-23 08:00:00', 'UTC'), Carbon::parse('2026-11-23 10:00:00', 'UTC')))->toBe(120);

    $this->travelTo(Carbon::parse('2026-11-23 09:00:00', 'UTC'));
    $quiet = collect(ShowSlackSyncHealth::make()->warnings(['newest_message_at' => $friday->toIso8601String(), 'files_failed' => 0], []))->firstWhere('type', 'quiet');
    expect($quiet['working_hours'])->toBe(3);

    $this->travelTo(Carbon::parse('2026-11-23 07:30:00', 'UTC'));
    expect(collect(ShowSlackSyncHealth::make()->warnings(['newest_message_at' => $friday->toIso8601String(), 'files_failed' => 0], []))->firstWhere('type', 'quiet'))->toBeNull()
        ->and(collect(ShowSlackSyncHealth::make()->warnings(['newest_message_at' => null, 'files_failed' => 0], []))->firstWhere('type', 'quiet'))->not->toBeNull();
});

test('every nightly Slack command records its run, with its totals or the error that stopped it', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/api/*' => Http::response(['ok' => true, 'members' => [], 'channels' => [], 'items' => [], 'bookmarks' => [], 'emoji' => []])]);

    foreach (['slack:sync-public-channels --history --catch-up', 'slack:sync-users', 'slack:sync-channel-members', 'slack:sync-pins-and-bookmarks', 'slack:sync-emojis'] as $commandLine) {
        $command = Str::before($commandLine, ' ');
        $before  = SlackSyncRun::where('command', $command)->max('id') ?? 0;

        $this->artisan($commandLine)->assertSuccessful();

        $run = SlackSyncRun::where('command', $command)->where('id', '>', $before)->sole();
        expect($run->finished_at)->not->toBeNull()
            ->and($run->error)->toBeNull()
            ->and($command === 'slack:sync-public-channels' ? $run->channels : $run->totals)->not->toBeNull();
    }

    Config::set('services.slack.notifications.bot_user_oauth_token', null);
    $before = SlackSyncRun::where('command', 'slack:sync-emojis')->max('id');
    expect(fn () => $this->artisan('slack:sync-emojis')->run())->toThrow(RuntimeException::class);
    $failed = SlackSyncRun::where('command', 'slack:sync-emojis')->where('id', '>', $before)->sole();
    expect($failed->error)->toBe('Slack bot token is not set')
        ->and($failed->finished_at)->not->toBeNull();
});

test('only the nightly channel sync warns when no run of it is recorded at all', function () {
    $runs = collect(array_keys(ShowSlackSyncHealth::NIGHTLY_COMMANDS))->map(fn (string $command) => ['command' => $command, 'status' => 'not_recorded', 'started_at' => null, 'error' => null])->all();

    $warnings = collect(ShowSlackSyncHealth::make()->warnings(['newest_message_at' => now()->toIso8601String(), 'files_failed' => 0], $runs));

    expect($warnings->pluck('type', 'command')->all())->toBe(['slack:sync-public-channels' => 'run_not_recorded']);
});
