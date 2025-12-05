<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Dec 2025 18:04:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Support\Facades\Redis;

it('lists keys via redis:keys command', function (): void {
    $redis = Redis::connection('cache');

    // Seed
    $redis->set('hello_1', 'x');
    $redis->set('hello_a', 'y');
    $redis->set('other_key', 'z');

    // Run
    $this->artisan('redis:keys', [
        'pattern' => 'hello_*',
        '--connection' => 'cache',
    ])
        ->expectsOutput('hello_1')
        ->expectsOutput('hello_a')
        ->doesntExpectOutput('other_key')
        ->expectsOutput('Total: 2')
        ->assertExitCode(0);

    // Cleanup (attempt raw and prefixed)
    $prefix = (string) config('database.redis.options.prefix').(string) config('cache.prefix');
    $redis->del(['hello_1', 'hello_a', 'other_key']);
    $redis->del([$prefix.'hello_1', $prefix.'hello_a', $prefix.'other_key']);
});
