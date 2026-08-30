<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Traits\WithArchiveOperations;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Downsamples the two per SKU stock history tables: beyond the retention window only the last
 * snapshot of each month stays in the operational database, every other day is moved to the
 * archive. Nothing is lost twice over, since the copy is verified row for row before the delete
 * and these rows are derived anyway, replayable from org_stock_movements by
 * CalculateOrgStockHistoricStockHistories.
 *
 * A whole date is the unit of work, never part of one: the readers decide where to look per day,
 * so a half moved day would be a day that reads as half empty from both databases. The month
 * keeper is the last date actually present in that month rather than the calendar last day, so a
 * month whose final days were never calculated still keeps a snapshot.
 *
 * org_stocks, locations and organisation_stock_histories are mirrored to the archive on every run
 * (231 MB together, against the tens of GB this frees). That is what keeps the read path cheap:
 * an archived day is served by the same SQL as a live one against another connection, instead of
 * every reader growing a second code path that joins the lookups back in PHP.
 *
 * The organisation and group level daily series is never touched, so dashboards and valuation
 * totals keep full daily history for every year regardless of the retention window.
 *
 * Telemetry is dropped for the run: thousands of queries a second buffer into multi megabyte
 * writes the ingest agent cannot take, and it dies mid run with a broken pipe. Done here rather
 * than with NIGHTWATCH_ENABLED, which a cached config ignores.
 */
class ArchiveStockHistories
{
    use AsAction;
    use WithArchiveOperations;

    public string $commandSignature = 'inventory:archive_stock_histories {--c|chunk=5000} {--dates=} {--months=} {--from=} {--until=} {--d|dry-run}';

    public string $commandDescription = 'Move per SKU stock history older than the retention window to the archive database, keeping one snapshot per month';

    public string $archiveConnection = 'archive';

    private const ADVISORY_LOCK_KEY = 826_041_503;

    private const DRY_RUN_SAMPLES = 12;

    private const HISTORY_TABLES = ['location_org_stock_histories', 'org_stock_histories'];

    private const MIRRORED_TABLES = ['org_stocks', 'locations', 'organisation_stock_histories'];

    private const ARCHIVE_INDEXES = [
        'org_stock_histories' => [
            'org_stock_histories_archive_stock_date_idx on "org_stock_histories" ("org_stock_id", "date")',
            'org_stock_histories_archive_org_history_idx on "org_stock_histories" ("organisation_stock_history_id")',
            'org_stock_histories_archive_date_idx on "org_stock_histories" ("date")',
        ],
        'location_org_stock_histories' => [
            'location_org_stock_histories_archive_stock_date_idx on "location_org_stock_histories" ("org_stock_id", "date")',
            'location_org_stock_histories_archive_history_idx on "location_org_stock_histories" ("org_stock_history_id")',
            'location_org_stock_histories_archive_date_idx on "location_org_stock_histories" ("date")',
        ],
        'org_stocks' => [
            'org_stocks_archive_organisation_idx on "org_stocks" ("organisation_id")',
        ],
        'organisation_stock_histories' => [
            'organisation_stock_histories_archive_org_date_idx on "organisation_stock_histories" ("organisation_id", "date")',
        ],
        'locations' => [],
    ];

    public function handle(
        int $chunkSize = 5000,
        ?int $dateLimit = null,
        ?int $months = null,
        ?string $from = null,
        ?string $until = null,
        bool $dryRun = false,
        ?Command $command = null
    ): int {
        Nightwatch::dontSample();

        $cutoff = $this->cutoff($months);
        $dates  = $this->eligibleDates($cutoff, $from, $until);

        if ($dateLimit) {
            $dates = array_slice($dates, 0, $dateLimit);
        }

        if (!$dates) {
            $command?->info('No eligible days to archive');

            return 0;
        }

        if ($dryRun) {
            return $this->reportDryRun($dates, $cutoff, $command);
        }

        $lockKey = $this->lockKey($from, $until);

        if (!DB::selectOne('select pg_try_advisory_lock(?) as locked', [$lockKey])->locked) {
            throw new Exception('Another stock history archive run holds the lock for this slice; refusing to start a second one.');
        }

        try {
            $this->assertArchiveIsNotProduction();
            $this->assertReplicationMeasurable();
            $this->prepareArchive($command);

            $progress = null;
            if ($command) {
                $command->info('Archiving '.count($dates).' days older than '.$cutoff->format('Y-m-d').', keeping one snapshot per month');
                $progress = $command->getOutput()->createProgressBar(count($dates));
                $progress->setFormat(' %current%/%max% days [%bar%] %percent:3s%%  elapsed %elapsed:6s%  eta %estimated:-6s%');
                $progress->start();
            }

            $archivedRows = 0;
            foreach ($dates as $date) {
                $this->waitForReplication($command, $progress);
                $archivedRows += $this->archiveDay($date, $chunkSize);
                $progress?->advance();
            }

            $progress?->finish();
            $command?->newLine();

            return $archivedRows;
        } finally {
            DB::selectOne('select pg_advisory_unlock(?)', [$lockKey]);
        }
    }

    /**
     * The first pass over nineteen years is far too big for one worker, so --from/--until slice it
     * and each slice locks on its own key: two workers on disjoint years run together, two on the
     * same slice still refuse. Distinct slices can collide onto one key and merely serialise,
     * which costs time rather than correctness.
     */
    private function lockKey(?string $from, ?string $until): int
    {
        if (!$from && !$until) {
            return self::ADVISORY_LOCK_KEY;
        }

        return self::ADVISORY_LOCK_KEY + 1 + (int) (sprintf('%u', crc32($from.'>'.$until)) % 1000);
    }

    private function cutoff(?int $months): Carbon
    {
        return now()->subMonths($months ?? config('archive.stock_history_retention_months'))->startOfDay();
    }

    /**
     * Driven by organisation_stock_histories, one row per organisation per day and 18k rows in
     * total, because a distinct scan for the same dates over the 150M row history tables would
     * cost more than the archiving itself. A day the driver does not know about is simply never
     * archived, which leaves data in place rather than losing it.
     *
     * The monthly keepers are chosen over every eligible day and only then narrowed to the slice,
     * so a worker handed a range that opens mid month cannot promote its own first day of that
     * month into a second keeper.
     *
     * @return array<int, string>
     */
    private function eligibleDates(Carbon $cutoff, ?string $from = null, ?string $until = null): array
    {
        $dates = DB::table('organisation_stock_histories')
            ->where('date', '<', $cutoff->format('Y-m-d'))
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        $monthKeepers = [];
        foreach ($dates as $date) {
            $monthKeepers[substr($date, 0, 7)] = $date;
        }

        $eligible = array_diff($dates, array_values($monthKeepers));

        return array_values(array_filter(
            $eligible,
            fn (string $date) => (!$from || $date >= $from) && (!$until || $date < $until)
        ));
    }

    private function prepareArchive(?Command $command): void
    {
        $archive = DB::connection($this->archiveConnection);

        foreach (array_merge(self::HISTORY_TABLES, self::MIRRORED_TABLES) as $table) {
            $this->ensureArchiveTable($archive, $table);
            foreach (self::ARCHIVE_INDEXES[$table] as $index) {
                $archive->statement("create index if not exists $index");
            }
        }

        $command?->info('Refreshing archive lookup mirrors');
        foreach (self::MIRRORED_TABLES as $table) {
            $this->mirrorTable($table);
        }
    }

    /**
     * The mirrors are a copy of small, entirely current tables, so they are replaced wholesale
     * rather than diffed. A SKU renamed today shows its new name against archived days from
     * tomorrow's run, which is how the operational database renders those days anyway.
     */
    private function mirrorTable(string $table): void
    {
        $archive = DB::connection($this->archiveConnection);

        $archive->transaction(function () use ($archive, $table) {
            $archive->table($table)->delete();

            $buffer = [];
            foreach (DB::table($table)->cursor() as $row) {
                $buffer[] = (array) $row;
                if (count($buffer) >= 500) {
                    $archive->table($table)->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                $archive->table($table)->insert($buffer);
            }
        });
    }

    private function archiveDay(string $date, int $chunkSize): int
    {
        $rows = 0;

        foreach (self::HISTORY_TABLES as $table) {
            $ids = DB::table($table)->where('date', $date)->pluck('id');

            foreach ($ids->chunk($chunkSize) as $chunk) {
                $this->copyToArchive($table, 'id', $chunk->values()->all());
            }

            $rows += $ids->count();
        }

        DB::transaction(function () use ($date) {
            foreach (self::HISTORY_TABLES as $table) {
                DB::table($table)->where('date', $date)->delete();
            }
        });

        return $rows;
    }

    /**
     * Sampled across the whole range rather than extrapolated from the newest day: a 2023 day holds
     * roughly three times the rows of a 2014 one, so measuring the top of the range and multiplying
     * by every day overstates the run by about half.
     *
     * @param array<int, string> $dates
     */
    private function reportDryRun(array $dates, Carbon $cutoff, ?Command $command): int
    {
        $samples   = $this->sampleDates($dates);
        $sampleRows = 0;

        foreach ($samples as $sample) {
            foreach (self::HISTORY_TABLES as $table) {
                $sampleRows += DB::table($table)->where('date', $sample)->count();
            }
        }

        $estimate = (int) round($sampleRows / count($samples) * count($dates));

        $command?->info('Retention cutoff '.$cutoff->format('Y-m-d'));
        $command?->info(count($dates).' days eligible, one snapshot per month stays');
        $command?->info(
            'Sampled '.count($samples).' days across the range at '.number_format((int) round($sampleRows / count($samples))).
            ' rows a day: roughly '.number_format($estimate).' rows to move'
        );

        return count($dates);
    }

    /**
     * @param array<int, string> $dates
     *
     * @return array<int, string>
     */
    private function sampleDates(array $dates): array
    {
        $wanted = min(self::DRY_RUN_SAMPLES, count($dates));
        $step   = (count($dates) - 1) / max($wanted - 1, 1);

        $samples = [];
        for ($i = 0; $i < $wanted; $i++) {
            $samples[] = $dates[(int) round($i * $step)];
        }

        return array_values(array_unique($samples));
    }

    public function asCommand(Command $command): int
    {
        $archived = $this->handle(
            chunkSize: (int) $command->option('chunk'),
            dateLimit: $command->option('dates') ? (int) $command->option('dates') : null,
            months: $command->option('months') ? (int) $command->option('months') : null,
            from: $command->option('from'),
            until: $command->option('until'),
            dryRun: (bool) $command->option('dry-run'),
            command: $command
        );

        if ($command->option('dry-run')) {
            return 0;
        }

        $command->info("Archived $archived stock history rows");
        $command->info('Run VACUUM (ANALYZE) org_stock_histories, location_org_stock_histories; after a large run, and pg_repack to give the disk back.');

        return 0;
    }
}
