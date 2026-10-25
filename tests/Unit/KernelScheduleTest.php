<?php

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;

function scheduledEventIds(Schedule $schedule): array
{
    $ids = [];
    foreach ($schedule->events() as $event) {
        $ids[] = $event->description ?: trim(preg_replace("/^.*artisan'\s*/", '', $event->command), " '");
    }

    return $ids;
}

function scheduledEventsById(Schedule $schedule): array
{
    $events = [];
    foreach ($schedule->events() as $event) {
        $id          = $event->description ?: trim(preg_replace("/^.*artisan'\s*/", '', $event->command), " '");
        $events[$id] = $event->expression;
    }

    return $events;
}

function rebuildSchedule(): Schedule
{
    $schedule   = new Schedule();
    $kernel     = app(Kernel::class);
    $reflection = new ReflectionMethod(Kernel::class, 'schedule');
    $reflection->setAccessible(true);
    $reflection->invoke($kernel, $schedule);

    return $schedule;
}

test('kernel commands() loads console routes without error', function () {
    $kernel     = app(Kernel::class);
    $reflection = new ReflectionMethod($kernel, 'commands');
    $reflection->setAccessible(true);

    $reflection->invoke($kernel);

    expect(true)->toBeTrue();
});

test('always-on schedules run regardless of master/slave config', function () {
    $events = scheduledEventsById(rebuildSchedule());

    expect($events['horizon:snapshot'])->toBe('*/5 * * * *')
        ->and($events['cloudflare:reload'])->toBe('0 0 * * *');
});

test('master-only schedules register when app.master is enabled', function () {
    config(['app.master' => true, 'app.slave' => false]);

    $events = scheduledEventIds(rebuildSchedule());

    expect($events)
        ->toContain('offer:update_status_from_dates')
        ->toContain('ebay:ping')
        ->not->toContain('queue:prune-failed --hours=168')
        ->not->toContain(\App\Actions\Reviews\AutoPublishReviews::class);
});

test('slave-only schedules register when app.slave is enabled', function () {
    config(['app.master' => false, 'app.slave' => true]);

    $events = scheduledEventIds(rebuildSchedule());

    expect($events)
        ->toContain('queue:prune-failed --hours=168')
        ->toContain(\App\Actions\Reviews\AutoPublishReviews::class)
        ->not->toContain('ebay:ping');
});

test('neither master nor slave schedules register when both flags are disabled', function () {
    config(['app.master' => false, 'app.slave' => false]);

    expect(scheduledEventIds(rebuildSchedule()))
        ->toContain('horizon:snapshot', 'cloudflare:reload')
        ->not->toContain(
            'offer:update_status_from_dates',
            'ebay:ping',
            'queue:prune-failed --hours=168',
            \App\Actions\Reviews\AutoPublishReviews::class,
        );
});

test('logged commands run in the background so a long one cannot delay the next, and keep the same log key in the finish process', function () {
    config(['app.master' => true, 'app.slave' => true]);

    $findCommand = fn (Schedule $schedule, string $command) => collect($schedule->events())->first(fn ($event) => str_contains((string) $event->command, $command));

    $first  = $findCommand(rebuildSchedule(), 'production:release_pre_pick');
    $second = $findCommand(rebuildSchedule(), 'production:release_pre_pick');
    $jobs   = collect(rebuildSchedule()->events())->filter(fn ($event) => $event instanceof Illuminate\Console\Scheduling\CallbackEvent);

    expect($first->runInBackground)->toBeTrue()
        ->and($findCommand(rebuildSchedule(), 'woo:ping_active_channel')->runInBackground)->toBeTrue()
        ->and($first->mutexName())->toBe($second->mutexName())
        ->and($jobs->every(fn ($event) => !$event->runInBackground))->toBeTrue();
});

test('a default overlap lock expires within ten runs so a killed run cannot block a task for a day, and an explicit expiry is kept', function () {
    config(['app.master' => true, 'app.slave' => true]);

    $schedule    = rebuildSchedule();
    $findCommand = fn (string $command) => collect($schedule->events())->first(fn ($event) => str_contains((string) $event->command, $command));

    expect($findCommand('chat:prune-agent-presence')->expiresAt)->toBe(10)
        ->and($findCommand('chat:alert-unclaimed')->expiresAt)->toBe(50)
        ->and($findCommand('production:release_pre_pick')->expiresAt)->toBe(100)
        ->and($findCommand('production:stop_sessions_after_shift')->expiresAt)->toBe(150)
        ->and($findCommand('chat:learn-knowledge')->expiresAt)->toBe(1440)
        ->and($findCommand('hydrate:org-stock-out-of-stock-forecast')->expiresAt)->toBe(360);
});
