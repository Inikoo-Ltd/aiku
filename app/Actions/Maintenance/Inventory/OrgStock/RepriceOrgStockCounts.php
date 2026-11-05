<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Oct 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStock;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues;
use App\Actions\Maintenance\Inventory\OrgStockMovement\RecalculateOrgStockHistoriesPostCostFix;
use App\Actions\Traits\WithStockHistoryArchiveWrite;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4274. Stock counts entered in the wrong unit are priced in the wrong unit too: EnvJ-09 is a
 * pack of 50 envelopes at £12.58, but staff counted single envelopes from 17 Jul 2025, so every
 * found, write-off, adjustment and stock check since then was valued at £12.58 an envelope. Those
 * counts that carry a value (not purchases, picks or production, nor Aurora's valueless audit
 * markers) are priced at the given cost per counted unit instead, the old rows copied to help4274_repriced_org_stock_movements first. Quantities stay as
 * counted; the SKU's running values, daily histories and the organisation stock value days are
 * worked out again. With --purchases the purchases are repriced too: INI-211, an Aurora purchase
 * of HHEX-25 put away as 768 packs of 6 at the pack price, then counted as 4,608 single units
 * after packed_in went from 6 to 1, so every unit was valued at the price of six.
 */
class RepriceOrgStockCounts
{
    use AsAction;
    use WithStockHistoryArchiveWrite;

    private const string BACKUP_TABLE = 'help4274_repriced_org_stock_movements';

    private const array COUNT_TYPES = [
        OrgStockMovementTypeEnum::FOUND,
        OrgStockMovementTypeEnum::WRITE_OFF,
        OrgStockMovementTypeEnum::ADJUSTMENT,
        OrgStockMovementTypeEnum::AUDIT,
    ];

    public function getCommandSignature(): string
    {
        return 'org_stock:reprice_counts {org_stock : org stock id} {--from= : first date to reprice} {--cost= : cost per counted unit} {--purchases : reprice purchases as well} {--dry-run}';
    }

    /**
     * @return array{movements: int, recorded: float, repriced: float, organisation_days_rolled_up: int}
     */
    public function handle(OrgStock $orgStock, string $from, float $cost, bool $dryRun, bool $withPurchases = false): array
    {
        $counts = $this->counts($orgStock, $from, $withPurchases)->get(['id', 'date', 'quantity', 'org_amount']);

        $report = [
            'movements'                   => $counts->count(),
            'recorded'                    => round((float)$counts->sum('org_amount'), 2),
            'repriced'                    => round($counts->sum(fn ($count) => $count->quantity * $cost), 2),
            'organisation_days_rolled_up' => 0,
        ];

        if ($dryRun || $counts->isEmpty()) {
            return $report;
        }

        DB::transaction(function () use ($orgStock, $counts, $cost) {
            DB::statement('create table if not exists '.self::BACKUP_TABLE.' (like org_stock_movements)');
            DB::statement(
                'insert into '.self::BACKUP_TABLE.' select * from org_stock_movements where id = any(?::bigint[]) and id not in (select id from '.self::BACKUP_TABLE.')',
                ['{'.$counts->pluck('id')->implode(',').'}']
            );

            foreach ($counts as $count) {
                $orgAmount = round($count->quantity * $cost, 3);
                DB::table('org_stock_movements')->where('id', $count->id)->update([
                    'org_amount'   => $orgAmount,
                    'grp_amount'   => round($orgAmount * $this->rate($orgStock, Carbon::parse($count->date)), 3),
                    'cost_per_sku' => $cost,
                ]);
            }
        });

        CalculateOrgStockMovementRunningValues::run($orgStock->id);
        RecalculateOrgStockHistoriesPostCostFix::run($orgStock->id, true);

        $organisationStockHistoryIds = collect();
        foreach ($this->stockHistoryWriteConnections() as $connection) {
            $organisationStockHistoryIds = $organisationStockHistoryIds->concat(
                DB::connection($connection)->table('org_stock_histories')
                    ->where('org_stock_id', $orgStock->id)
                    ->where('date', '>=', Carbon::parse($from)->format('Y-m-d'))
                    ->whereNotNull('organisation_stock_history_id')
                    ->distinct()
                    ->pluck('organisation_stock_history_id')
            );
        }
        $organisationStockHistoryIds = $organisationStockHistoryIds->unique()->values();

        foreach ($organisationStockHistoryIds as $organisationStockHistoryId) {
            OrganisationStockHistoryHydrateFromOrgStockHistories::run($organisationStockHistoryId);
        }
        $report['organisation_days_rolled_up'] = $organisationStockHistoryIds->count();

        return $report;
    }

    private function counts(OrgStock $orgStock, string $from, bool $withPurchases = false): Builder
    {
        $types = $withPurchases ? [...self::COUNT_TYPES, OrgStockMovementTypeEnum::PURCHASE] : self::COUNT_TYPES;

        return DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStock->id)
            ->whereIn('type', array_map(fn (OrgStockMovementTypeEnum $type) => $type->value, $types))
            ->where('quantity', '!=', 0)
            ->where('org_amount', '!=', 0)
            ->where('date', '>=', Carbon::parse($from)->startOfDay())
            ->orderBy('date')
            ->orderBy('id');
    }

    private function rate(OrgStock $orgStock, Carbon $date): float
    {
        return GetHistoricCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency, $date) ?? 1.0;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $orgStock = OrgStock::findOrFail($command->argument('org_stock'));
        $from     = (string)$command->option('from');
        $cost     = (float)$command->option('cost');
        $dryRun   = (bool)$command->option('dry-run');
        $withPurchases = (bool)$command->option('purchases');

        if (!$from || $cost <= 0) {
            $command->error('--from and a positive --cost are required');

            return 1;
        }

        $command->info(($dryRun ? '[dry run] ' : '')."$orgStock->code $orgStock->name, counts from $from at $cost each");

        if ($dryRun) {
            $command->table(
                ['Movement', 'Date', 'Quantity', 'Recorded', 'Repriced'],
                $this->counts($orgStock, $from, $withPurchases)->get(['id', 'date', 'type', 'quantity', 'org_amount'])->map(fn ($count) => [
                    $count->id.' '.$count->type,
                    substr($count->date, 0, 10),
                    (float)$count->quantity,
                    round((float)$count->org_amount, 2),
                    round($count->quantity * $cost, 2),
                ])->all()
            );
        }

        $report = $this->handle($orgStock, $from, $cost, $dryRun, $withPurchases);
        $command->table(['What', 'Value'], collect($report)->map(fn (int|float $value, string $what) => [$what, $value])->values()->all());

        return 0;
    }
}
