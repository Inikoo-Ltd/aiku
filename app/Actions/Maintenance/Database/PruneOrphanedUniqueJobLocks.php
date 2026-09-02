<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 17:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * One-time cleanup after the 28 Aug 2026 Redis incident: unique-job locks created
 * before BoundedUniqueJobDecorator have no TTL and never release when their job is
 * gone, leaving millions of dead keys. Every lock created since carries a TTL, so a
 * laravel_unique_job key with TTL -1 is always an orphan and safe to unlink.
 */
class PruneOrphanedUniqueJobLocks
{
    use AsAction;

    public string $commandSignature = 'maintenance:prune_orphaned_unique_job_locks {--d|dry-run : Count what would be deleted without deleting}';
    public string $commandDescription = 'Unlink laravel_unique_job locks that have no TTL (orphans from before BoundedUniqueJobDecorator)';

    /**
     * @return array{scanned: int, orphaned: int, deleted: int}
     */
    public function handle(bool $dryRun = false, ?Command $command = null): array
    {
        $client = Redis::connection('default')->client();
        $client->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $prefix = (string) $client->getOption(\Redis::OPT_PREFIX);

        $scanned  = 0;
        $orphaned = 0;
        $deleted  = 0;

        $iterator = null;
        while (false !== ($keys = $client->scan($iterator, '*laravel_unique_job*', 1000))) {
            if ($keys === []) {
                continue;
            }
            if ($prefix !== '') {
                $keys = array_map(
                    fn (string $key) => str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key,
                    $keys
                );
            }
            $scanned += count($keys);

            $pipe = $client->pipeline();
            foreach ($keys as $key) {
                $pipe->ttl($key);
            }
            $ttls = $pipe->exec();

            $orphans = array_values(array_filter(
                $keys,
                fn (int $index) => ($ttls[$index] ?? 0) === -1,
                ARRAY_FILTER_USE_KEY
            ));
            $orphaned += count($orphans);

            if ($orphans && !$dryRun) {
                $deleted += (int) $client->unlink($orphans);
            }

            if ($scanned % 100000 < 1000) {
                $command?->info("scanned $scanned, orphaned $orphaned, deleted $deleted");
            }
        }

        $command?->info(($dryRun ? '[dry-run] ' : '')."done: scanned $scanned, orphaned $orphaned, deleted $deleted");

        return ['scanned' => $scanned, 'orphaned' => $orphaned, 'deleted' => $deleted];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $this->handle((bool) $command->option('dry-run'), $command);

        return 0;
    }
}
