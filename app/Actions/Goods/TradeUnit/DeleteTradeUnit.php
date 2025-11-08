<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Nov 2025 15:13:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Nov 2025 13:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 */

namespace App\Actions\Goods\TradeUnit;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteTradeUnit
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'trade_units:delete {identifier} {--by=id} {--hard} {--force} {--dry-run} {--reason=}';
    }

    /**
     * @throws \Throwable
     */
    public function handle(TradeUnit $tradeUnit, array $options = []): array
    {
        $hard   = (bool) Arr::get($options, 'hard', false);
        $force  = (bool) Arr::get($options, 'force', false);
        $dryRun = (bool) Arr::get($options, 'dry_run', false);
        $reason = Arr::get($options, 'reason');

        // Gather linkage counts
        $activeStocks = $tradeUnit->stocks()
            ->whereIn('state', [StockStateEnum::ACTIVE, StockStateEnum::DISCONTINUING])
            ->count();

        $linkedProducts = $tradeUnit->products()->count();

        $blockers = [];
        if (!$force) {
            if ($activeStocks > 0) {
                $blockers[] = "Has $activeStocks active/discontinuing stock(s)";
            }
            if ($linkedProducts > 0) {
                $blockers[] = "Linked to $linkedProducts product(s)";
            }
        }

        // Prepare counts of attachments for reporting
        $counts = [
            'model_has_trade_units' => DB::table('model_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->count(),
            'trade_unit_has_ingredients' => DB::table('trade_unit_has_ingredients')->where('trade_unit_id', $tradeUnit->id)->count(),
            'model_has_barcodes' => DB::table('model_has_barcodes')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->count(),
            'model_has_media' => DB::table('model_has_media')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->count(),
            'model_has_tags' => DB::table('model_has_tags')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->count(),
            'model_has_brands' => DB::table('model_has_brands')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->count(),
        ];

        if (!$force && count($blockers) > 0) {
            return [
                'ok' => false,
                'deleted' => false,
                'hard' => $hard,
                'dry_run' => $dryRun,
                'blockers' => $blockers,
                'counts' => $counts,
            ];
        }

        if ($dryRun) {
            return [
                'ok' => true,
                'deleted' => false,
                'hard' => $hard,
                'dry_run' => true,
                'blockers' => $blockers,
                'counts' => $counts,
            ];
        }

        DB::beginTransaction();
        try {
            // Persist reason on soft delete in data JSON for auditing/traceability
            if ($reason) {
                // JSON path update supported in modern Laravel for pg/mysql
                try {
                    $tradeUnit->updateQuietly(['data->deleted_reason' => $reason]);
                } catch (\Throwable) {
                    // Fallback: merge data array
                    $data = $tradeUnit->data ?? [];
                    $data['deleted_reason'] = $reason;
                    $tradeUnit->updateQuietly(['data' => $data]);
                }
            }

            if ($hard) {

                DB::table('model_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->delete();
                DB::table('trade_unit_has_ingredients')->where('trade_unit_id', $tradeUnit->id)->delete();
                DB::table('model_has_barcodes')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->delete();
                DB::table('model_has_media')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->delete();
                DB::table('model_has_tags')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->delete();
                DB::table('model_has_brands')->where('model_type', 'TradeUnit')->where('model_id', $tradeUnit->id)->delete();



                // Nullify direct foreign keys / denormalized fields
                $nulls = [
                    'barcode_id' => null,
                    'barcode' => null,
                    'front_image_id' => null,
                    '34_image_id' => null,
                    'left_image_id' => null,
                    'right_image_id' => null,
                    'back_image_id' => null,
                    'top_image_id' => null,
                    'bottom_image_id' => null,
                    'size_comparison_image_id' => null,
                    'lifestyle_image_id' => null,
                    'art1_image_id' => null,
                    'art2_image_id' => null,
                    'art3_image_id' => null,
                    'art4_image_id' => null,
                    'art5_image_id' => null,
                ];
                $tradeUnit->updateQuietly($nulls);

                $tradeUnit->stats()->forceDelete();
                $tradeUnit->forceDelete();
            } else {
                $tradeUnit->stats()->delete();
                $tradeUnit->delete();
            }

            DB::commit();

            return [
                'ok' => true,
                'deleted' => true,
                'hard' => $hard,
                'dry_run' => false,
                'blockers' => [],
                'counts' => $counts,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'ok' => false,
                'deleted' => false,
                'hard' => $hard,
                'dry_run' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Console entrypoint.
     *
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $identifier = (string) $command->argument('identifier');
        $by = (string) $command->option('by');
        $hard = (bool) $command->option('hard');
        $force = (bool) $command->option('force');
        $dryRun = (bool) $command->option('dry-run');
        $reason = $command->option('reason');

        $tradeUnit = $this->resolveTradeUnit($identifier, $by);
        if (!$tradeUnit) {
            $command->error("TradeUnit not found by $by: $identifier");
            return 1;
        }

        DB::disableQueryLog();

        $command->info(($dryRun ? '[DRY-RUN] ' : '').'Deleting Trade Unit:');
        $command->line(" - ID: $tradeUnit->id");
        $command->line(" - Code: $tradeUnit->code");
        $command->line(" - Name: $tradeUnit->name");

        $result = $this->handle($tradeUnit, [
            'hard' => $hard,
            'force' => $force,
            'dry_run' => $dryRun,
            'reason' => $reason,
        ]);

        if (!$result['ok']) {
            if (!empty($result['blockers'])) {
                $command->warn('Deletion blocked by:');
                foreach ($result['blockers'] as $b) {
                    $command->line("  - $b");
                }
            }
            if (isset($result['error'])) {
                $command->error('Error: '.$result['error']);
            }
            return 2;
        }

        if ($dryRun) {
            $command->info('Dry run complete. Summary of linked records:');
            $this->printCountsTable($command, Arr::get($result, 'counts', []));
            return 0;
        }

        $command->info('Deletion completed.');
        $this->printCountsTable($command, Arr::get($result, 'counts', []));

        return 0;
    }

    protected function printCountsTable(Command $command, array $counts): void
    {
        $rows = [];
        foreach ($counts as $k => $v) {
            $rows[] = [$k, $v];
        }
        $command->table(['Relation/Pivot', 'Count'], $rows);
    }

    protected function resolveTradeUnit(string $identifier, string $by = 'id'): ?TradeUnit
    {
        return match ($by) {
            'slug' => TradeUnit::withTrashed()->where('slug', $identifier)->first(),
            'source_id' => TradeUnit::withTrashed()->where('source_id', $identifier)->first(),
            default => TradeUnit::withTrashed()->find((int) $identifier),
        };
    }



}
