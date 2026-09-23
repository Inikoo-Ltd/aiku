<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Dec 2025 17:54:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Dec 2025 17:55:00 MYT
 */

namespace App\Actions\Helpers;

use Illuminate\Console\Command;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRedisKeysByPattern
{
    use AsAction;


    public function handle(string $pattern, RedisConnection|string $connection = 'cache'): array
    {
        $redis = \is_string($connection) ? Redis::connection($connection) : $connection;

        // Build likely prefixes but do not assume they are always applied at the transport level
        $globalPrefix = (string)config('database.redis.options.prefix', '');
        $cachePrefix  = (\is_string($connection) ? $connection === 'cache' : $this->isCacheConnection($redis))
            ? (string)config('cache.prefix', '')
            : '';

        $keys = [];


        $isPhpRedis = $redis instanceof PhpRedisConnection;
        $match      = ($isPhpRedis ? $globalPrefix : '').$cachePrefix.$pattern;
        $cursor     = $isPhpRedis ? null : '0';

        do {
            $result = $redis->scan($cursor, [
                'match' => $match,
                'count' => 1000,
            ]);
            if (!\is_array($result) || count($result) !== 2) {
                break;
            }
            $cursor = $result[0];
            foreach ($result[1] ?? [] as $k) {
                $keys[] = $this->stripAllKnownPrefixes($k, $globalPrefix, $cachePrefix);
            }
        } while ((string)$cursor !== '0');

        // Ensure unique values in the case of duplicates across scans
        return array_values(array_unique($keys));
    }

    protected function stripPrefix(string $key, string $prefix): string
    {
        if ($prefix !== '' && str_starts_with($key, $prefix)) {
            return substr($key, strlen($prefix));
        }
        return $key;
    }

    protected function stripAllKnownPrefixes(string $key, string $globalPrefix, string $cachePrefix): string
    {
        $key = $this->stripPrefix($key, $globalPrefix.$cachePrefix);
        return $this->stripPrefix($key, $globalPrefix);

    }

    protected function isCacheConnection(RedisConnection $connection): bool
    {
        // Best-effort: compare connection name when available
        try {
            return method_exists($connection, 'getName') && $connection->getName() === 'cache';
        } catch (\Throwable) {
            return false;
        }
    }

    public function getCommandSignature(): string
    {
        return 'redis:keys {pattern} {--connection=cache}';
    }

    public function asCommand(Command $command): int
    {
        $pattern    = (string)$command->argument('pattern');
        $connection = (string)$command->option('connection');

        $keys = $this->handle($pattern, $connection);

        foreach ($keys as $key) {
            $command->line($key);
        }

        $command->info('Total: '.count($keys));

        return 0;

    }
}
