<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * NightOwl keeps long raw retention on neon's HDD array. A daily partition stops receiving rows once its
 * day is over, but autovacuum keeps revisiting it; hundreds of them starved the disks on 30 Aug 2026.
 * Freezing a cold partition once and switching its autovacuum off makes old history cost no I/O.
 *
 * Budgeted per run so a backlog clears over several nights instead of in one I/O spike.
 */
class FreezeColdNightOwlPartitions extends Command
{
    protected $signature = 'nightowl:freeze-cold-partitions
                           {--days=3 : Only partitions whose day ended at least this many days ago}
                           {--minutes=60 : Stop starting new partitions after this many minutes}
                           {--dry-run : List the partitions that would be frozen}';

    protected $description = 'Freeze cold NightOwl raw partitions and switch their autovacuum off';

    public function handle(): int
    {
        Nightwatch::dontSample();

        $connection = DB::connection('nightowl');

        $partitions = $connection->select(
            "SELECT c.relname, c.reloptions::text AS reloptions
             FROM pg_inherits i
             JOIN pg_class c ON c.oid = i.inhrelid
             JOIN pg_class p ON p.oid = i.inhparent
             WHERE p.relkind = 'p' AND p.relname LIKE 'nightowl\\_%\\_v2'"
        );

        $coldPartitions = self::coldPartitions(
            $partitions,
            now()->utc()->startOfDay()->subDays((int)$this->option('days'))->toDateString()
        );

        if ($this->option('dry-run')) {
            foreach ($coldPartitions as $partition) {
                $this->line($partition);
            }
            $this->line(count($coldPartitions).' partitions would be frozen');

            return 0;
        }

        $deadline = now()->addMinutes((int)$this->option('minutes'));
        $frozen   = 0;

        foreach ($coldPartitions as $partition) {
            if (now()->greaterThan($deadline)) {
                break;
            }

            $startedAt = microtime(true);
            $connection->statement("VACUUM (FREEZE) {$partition}");
            $connection->statement("ALTER TABLE {$partition} SET (autovacuum_enabled = false)");
            $frozen++;

            $this->line(sprintf('%s frozen in %.1fs', $partition, microtime(true) - $startedAt));
        }

        $this->line("frozen {$frozen}, left for later ".(count($coldPartitions) - $frozen));

        return 0;
    }

    /**
     * @param  array<int, object{relname: string, reloptions: ?string}>  $partitions
     * @return list<string> oldest day first
     */
    public static function coldPartitions(array $partitions, string $lastColdDay): array
    {
        $coldPartitions = [];

        foreach ($partitions as $partition) {
            if (!preg_match('/^nightowl_[a-z_]+_v2_p(\d{8})$/', $partition->relname, $matches)) {
                continue;
            }

            if (str_contains((string)$partition->reloptions, 'autovacuum_enabled=false')) {
                continue;
            }

            $day = substr($matches[1], 0, 4).'-'.substr($matches[1], 4, 2).'-'.substr($matches[1], 6, 2);

            if ($day < $lastColdDay) {
                $coldPartitions[$partition->relname] = $day;
            }
        }

        uksort($coldPartitions, fn (string $a, string $b) => [$coldPartitions[$a], $a] <=> [$coldPartitions[$b], $b]);

        return array_keys($coldPartitions);
    }
}
