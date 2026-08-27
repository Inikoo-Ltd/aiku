<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\History;

use App\Actions\Traits\WithArchiveOperations;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves dead audit trails to the archive database: everything belonging to a closed shop
 * (audits.shop_id covers every audited model of that shop) and the audits of discontinued
 * products and org stocks in live shops, and the Aurora loop noise (see auroraLoopObjects).
 * The History tab falls back to the archive when the operational database has no rows for a
 * record, so the trail stays readable.
 *
 * Unlike dispatched emails there are no child tables (nothing holds a FK onto audits) and no
 * stats baselines: the number_audits* sysadmin counters deliberately mean live audits only.
 * There is no un-archive path either — a reopened shop or relaunched product starts a clean
 * trail, with the archived history reachable through the fallback and named in the footer.
 */
class ArchiveAudits
{
    use AsAction;
    use WithArchiveOperations;

    public string $commandSignature = 'helpers:archive_audits {--c|chunk=5000} {--l|limit=} {--d|dry-run} {--closed-shops} {--discontinued} {--aurora-loops}';

    public string $commandDescription = 'Copy audits of closed shops, discontinued products/org stocks and Aurora loop noise to the archive database and delete them';

    public string $archiveConnection = 'archive';

    private const ADVISORY_LOCK_KEY = 826_041_502;

    private const AURORA_IMPORT_URL = 'artisan fetch:histories%';

    private const AURORA_LOOP_MIN_AUDITS = 1000;

    private const AURORA_LOOP_MAX_DISTINCT_RATIO = 0.05;

    private const AURORA_LOOP_TYPES = ['Product', 'TradeUnit', 'OrgStock', 'Barcode', 'StockDelivery'];

    /** @var array<string, array<int, int>>|null */
    private ?array $auroraLoopObjects = null;

    public function handle(
        int $chunkSize = 5000,
        ?int $limit = null,
        bool $closedShops = true,
        bool $discontinued = true,
        bool $auroraLoops = false,
        bool $dryRun = false,
        ?Command $command = null
    ): int {
        if (!$closedShops && !$discontinued && !$auroraLoops) {
            throw new Exception('Nothing selected: enable at least one of closed shops, discontinued or Aurora loops.');
        }

        if ($dryRun) {
            $total = $this->eligibleAudits($closedShops, $discontinued, $auroraLoops)->count();
            $command?->info("Dry run: $total audits would be archived");

            return $total;
        }

        if (!DB::selectOne('select pg_try_advisory_lock(?) as locked', [self::ADVISORY_LOCK_KEY])->locked) {
            throw new Exception('Another audit archive run holds the lock; refusing to start a second one.');
        }

        $this->assertArchiveIsNotProduction();
        $this->assertReplicationMeasurable();
        $this->ensureArchiveTable(DB::connection($this->archiveConnection), 'audits');
        DB::connection($this->archiveConnection)->statement(
            'create index if not exists audits_auditable_archive_idx on "audits" ("auditable_type", "auditable_id")'
        );

        $progress = null;
        if ($command) {
            $eligible = $this->eligibleAudits($closedShops, $discontinued, $auroraLoops)->count();

            if ($eligible === 0) {
                DB::selectOne('select pg_advisory_unlock(?)', [self::ADVISORY_LOCK_KEY]);
                $command->info('No eligible audits to archive');

                return 0;
            }

            $progress = $command->getOutput()->createProgressBar($limit ? min($limit, $eligible) : $eligible);
            $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  elapsed %elapsed:6s%  eta %estimated:-6s%');
            $progress->start();
        }

        $archivedTotal = 0;
        $lastId        = 0;
        while (true) {
            $batchSize = $limit ? min($chunkSize, $limit - $archivedTotal) : $chunkSize;
            if ($batchSize <= 0) {
                break;
            }

            $this->waitForReplication($command, $progress);

            $auditIds = $this->eligibleAudits($closedShops, $discontinued, $auroraLoops)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id')->all();

            if (!$auditIds) {
                break;
            }
            $lastId = end($auditIds);

            $this->copyToArchive('audits', 'id', $auditIds);

            DB::transaction(function () use ($auditIds) {
                DB::table('audits')->whereIn('id', $auditIds)->delete();
            });

            $archivedTotal += count($auditIds);
            $progress?->advance(count($auditIds));
        }

        $progress?->finish();
        $command?->newLine();

        DB::selectOne('select pg_advisory_unlock(?)', [self::ADVISORY_LOCK_KEY]);

        return $archivedTotal;
    }

    private function eligibleAudits(bool $closedShops, bool $discontinued, bool $auroraLoops = false): Builder
    {
        return DB::table('audits')->where(function (Builder $query) use ($closedShops, $discontinued, $auroraLoops) {
            if ($closedShops) {
                $query->orWhereIn(
                    'shop_id',
                    DB::table('shops')->select('id')->where('state', ShopStateEnum::CLOSED->value)
                );
            }
            if ($discontinued) {
                $query->orWhere(function (Builder $subQuery) {
                    $subQuery->where('auditable_type', 'Product')
                        ->whereIn(
                            'auditable_id',
                            DB::table('products')->select('id')->where('state', ProductStateEnum::DISCONTINUED->value)
                        );
                });
                $query->orWhere(function (Builder $subQuery) {
                    $subQuery->where('auditable_type', 'OrgStock')
                        ->whereIn(
                            'auditable_id',
                            DB::table('org_stocks')->select('id')->where('state', OrgStockStateEnum::DISCONTINUED->value)
                        );
                });
            }
            if ($auroraLoops) {
                foreach ($this->auroraLoopObjects() as $auditableType => $auditableIds) {
                    $query->orWhere(function (Builder $subQuery) use ($auditableType, $auditableIds) {
                        $subQuery->where('auditable_type', $auditableType)
                            ->whereIn('auditable_id', $auditableIds)
                            ->where('url', 'like', self::AURORA_IMPORT_URL);
                    });
                }
            }
        });
    }

    /**
     * Catalogue records whose Aurora-imported history is the artefact of the Aurora runaway loop:
     * two processes rewrote the same handful of attributes at each other every twenty minutes for
     * years. A looping object holds thousands of imported audits that repeat a couple of hundred
     * distinct transitions; a genuinely busy record has almost as many transitions as audits, so
     * the distinct ratio, not the row count alone, is what separates them.
     *
     * @return array<string, array<int, int>>
     */
    private function auroraLoopObjects(): array
    {
        if ($this->auroraLoopObjects !== null) {
            return $this->auroraLoopObjects;
        }

        $rows = DB::table('audits')
            ->select('auditable_type', 'auditable_id')
            ->where('url', 'like', self::AURORA_IMPORT_URL)
            ->whereIn('auditable_type', self::AURORA_LOOP_TYPES)
            ->groupBy('auditable_type', 'auditable_id')
            ->havingRaw('count(*) > ?', [self::AURORA_LOOP_MIN_AUDITS])
            ->havingRaw(
                'count(distinct (old_values::text || \'>\' || new_values::text))::numeric / count(*) < ?',
                [self::AURORA_LOOP_MAX_DISTINCT_RATIO]
            )
            ->get();

        $objects = [];
        foreach ($rows as $row) {
            $objects[$row->auditable_type][] = (int) $row->auditable_id;
        }

        return $this->auroraLoopObjects = $objects;
    }

    public function asCommand(Command $command): int
    {
        $onlyClosedShops  = (bool) $command->option('closed-shops');
        $onlyDiscontinued = (bool) $command->option('discontinued');
        $onlyAuroraLoops  = (bool) $command->option('aurora-loops');
        $all              = !$onlyClosedShops && !$onlyDiscontinued && !$onlyAuroraLoops;

        $archived = $this->handle(
            chunkSize: (int) $command->option('chunk'),
            limit: $command->option('limit') ? (int) $command->option('limit') : null,
            closedShops: $all || $onlyClosedShops,
            discontinued: $all || $onlyDiscontinued,
            auroraLoops: $all || $onlyAuroraLoops,
            dryRun: (bool) $command->option('dry-run'),
            command: $command
        );

        $command->info(($command->option('dry-run') ? 'Would archive' : 'Archived')." $archived audits");
        $command->info('Run VACUUM (ANALYZE) audits; before starting another large run.');

        return 0;
    }
}
