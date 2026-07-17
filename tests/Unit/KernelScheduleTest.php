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
        $id = $event->description ?: trim(preg_replace("/^.*artisan'\s*/", '', $event->command), " '");
        $events[$id] = $event->expression;
    }

    return $events;
}

function rebuildSchedule(): Schedule
{
    $schedule = new Schedule();
    $kernel = app(Kernel::class);
    $reflection = new ReflectionMethod(Kernel::class, 'schedule');
    $reflection->setAccessible(true);
    $reflection->invoke($kernel, $schedule);

    return $schedule;
}

test('kernel commands() loads console routes without error', function () {
    $kernel = app(Kernel::class);
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
        ->toContain(\App\Actions\Discounts\Offer\ActivateScheduledOffers::class)
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
        ->not->toContain(\App\Actions\Discounts\Offer\ActivateScheduledOffers::class)
        ->not->toContain('ebay:ping');
});

test('neither master nor slave schedules register when both flags are disabled', function () {
    config(['app.master' => false, 'app.slave' => false]);

    expect(rebuildSchedule()->events())->toHaveCount(2);
});
