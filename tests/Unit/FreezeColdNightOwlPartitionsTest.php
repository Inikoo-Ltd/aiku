<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Console\Commands\FreezeColdNightOwlPartitions;

test('only daily v2 partitions older than the last cold day are picked, oldest first', function () {
    $partitions = [
        (object)['relname' => 'nightowl_queries_v2_p20260911', 'reloptions' => null],
        (object)['relname' => 'nightowl_jobs_v2_p20260909', 'reloptions' => null],
        (object)['relname' => 'nightowl_cache_events_v2_p20260909', 'reloptions' => '{fillfactor=70}'],
        (object)['relname' => 'nightowl_queries_v2_p20260910', 'reloptions' => null],
        (object)['relname' => 'nightowl_queries_v2_pdefault', 'reloptions' => null],
        (object)['relname' => 'nightowl_queries_v2_phistoric', 'reloptions' => null],
        (object)['relname' => 'nightowl_query_rollups', 'reloptions' => null],
    ];

    expect(FreezeColdNightOwlPartitions::coldPartitions($partitions, '2026-09-11'))->toBe([
        'nightowl_cache_events_v2_p20260909',
        'nightowl_jobs_v2_p20260909',
        'nightowl_queries_v2_p20260910',
    ]);
});

test('partitions already frozen are skipped', function () {
    $partitions = [
        (object)['relname' => 'nightowl_logs_v2_p20260901', 'reloptions' => '{autovacuum_enabled=false}'],
        (object)['relname' => 'nightowl_logs_v2_p20260902', 'reloptions' => null],
    ];

    expect(FreezeColdNightOwlPartitions::coldPartitions($partitions, '2026-09-11'))->toBe(['nightowl_logs_v2_p20260902']);
});
