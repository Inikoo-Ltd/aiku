<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Maintenance\Database\PruneOrphanedUniqueJobLocks;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->redis   = Redis::connection('default');
    $this->orphan  = 'test_laravel_unique_job:orphan';
    $this->bounded = 'test_laravel_unique_job:bounded';
    $this->redis->set($this->orphan, 1);
    $this->redis->setex($this->bounded, 3600, 1);
});

afterEach(function () {
    $this->redis->del($this->orphan, $this->bounded);
});

test('locks without ttl are unlinked, bounded locks survive', function () {
    $result = PruneOrphanedUniqueJobLocks::run();

    expect($result['deleted'])->toBeGreaterThanOrEqual(1)
        ->and($this->redis->exists($this->orphan))->toBe(0)
        ->and($this->redis->exists($this->bounded))->toBe(1);
});

test('dry run deletes nothing', function () {
    $result = PruneOrphanedUniqueJobLocks::run(dryRun: true);

    expect($result['orphaned'])->toBeGreaterThanOrEqual(1)
        ->and($result['deleted'])->toBe(0)
        ->and($this->redis->exists($this->orphan))->toBe(1);
});
