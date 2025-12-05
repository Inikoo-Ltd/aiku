<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Dec 2025 17:54:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Helpers\GetRedisKeysByPattern;
use Illuminate\Support\Facades\Redis;

it('returns keys matching pattern on cache connection', function (): void {
    // Arrange: seed keys directly in Redis using the same prefixes as the action
    $redis = Redis::connection('cache');
    // Write without manual prefixes; client/config may apply them automatically
    $redis->set('hello_1', 'x');
    $redis->set('hello_a', 'y');
    $redis->set('other_key', 'z');

    // Act
    $keys = GetRedisKeysByPattern::run('hello_*', 'cache');

    // Assert
    expect($keys)->toContain('hello_1', 'hello_a');
    expect($keys)->not->toContain('other_key');

    // Cleanup
    // Cleanup tries both raw and prefixed variants
    $prefix = (string) config('database.redis.options.prefix').(string) config('cache.prefix');
    $redis->del(['hello_1', 'hello_a', 'other_key']);
    $redis->del([$prefix.'hello_1', $prefix.'hello_a', $prefix.'other_key']);
});
