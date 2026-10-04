<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->configuredConnections = [
        'aiku'           => config('database.connections.aiku'),
        'aiku_no_sticky' => config('database.connections.aiku_no_sticky'),
    ];
});

afterEach(function () {
    putenv('PROCESS_DB_READ_HOSTS');
    foreach ($this->configuredConnections as $connection => $configuration) {
        config(["database.connections.$connection" => $configuration]);
    }
});

test('processes read from the trimmed hosts in PROCESS_DB_READ_HOSTS on both connections', function () {
    putenv('PROCESS_DB_READ_HOSTS=10.0.0.8, 10.0.0.9,');

    (new AppServiceProvider(app()))->register();

    expect(config('database.connections.aiku.read.host'))->toBe(['10.0.0.8', '10.0.0.9'])
        ->and(config('database.connections.aiku_no_sticky.read.host'))->toBe(['10.0.0.8', '10.0.0.9'])
        ->and(config('database.connections.aiku.write.host'))->toBe($this->configuredConnections['aiku']['write']['host']);
});

test('without PROCESS_DB_READ_HOSTS the read hosts stay as configured', function () {
    (new AppServiceProvider(app()))->register();

    expect(config('database.connections.aiku.read.host'))->toBe($this->configuredConnections['aiku']['read']['host']);
});

test('each queued job starts reading the replica again even after an earlier job wrote', function () {
    putenv('PROCESS_DB_READ_HOSTS=10.0.0.8');
    (new AppServiceProvider(app()))->register();

    DB::connection('aiku')->recordsHaveBeenModified();

    event(new JobProcessing('redis', mock(Job::class)->shouldIgnoreMissing([])));

    expect(DB::connection('aiku')->hasModifiedRecords())->toBeFalse();
});
