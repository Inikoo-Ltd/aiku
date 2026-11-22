<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:32:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateStockValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateValueInLocations;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\LocationOrgStockHistory;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHistory;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateOrgStockCurrentStockHistories implements ShouldBeUnique
{
    use AsAction;
    use CalculatesOrgStockHistories {
        getLppPerSku as queryLppPerSku;
        openingOnHand as queryOpeningOnHand;
    }

    public bool $debug = false;
    public int $hydrateDelay = 60;

    /**
     * @var array{purchases: array<int, list<object>>, openingOnHand: array<int, float>}|null movements read once for the org stocks being calculated
     */
    private ?array $preloaded = null;

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }

    public function asJob(?int $orgStockId): void
    {
        $this->hydrateDelay = 15;
        $this->handle($orgStockId);
    }

    public function handle(int|OrgStock|null $orgStock): array
    {
        if (!$orgStock instanceof OrgStock) {
            $orgStock = $orgStock ? OrgStock::find($orgStock) : null;
        }
        if (!$orgStock) {
            return [];
        }

        return $this->calculateOrgStocks(collect([$orgStock]))[$orgStock->id];
    }

    /**
     * @param Collection<int, OrgStock> $orgStocks
     *
     * @return array<int, array> the location data written for each org stock id
     */
    public function calculateOrgStocks(Collection $orgStocks): array
    {
        $date        = Carbon::now();
        $orgStockIds = $orgStocks->pluck('id')->all();

        $this->preloaded = [
            'purchases'     => $this->preloadPurchases($orgStockIds),
            'openingOnHand' => $this->preloadOpeningOnHand($orgStocks),
        ];

        try {
            $calculations = [];
            foreach ($orgStocks as $orgStock) {
                $calculations[$orgStock->id] = $this->calculateOrgStock($orgStock, $date);
            }
        } finally {
            $this->preloaded = null;
        }

        $this->persistCalculations($date, $orgStocks, $calculations);

        foreach ($orgStocks as $orgStock) {
            $this->updateOrgStockCosts($orgStock, $calculations[$orgStock->id]['costPerSku'], $calculations[$orgStock->id]['fifoPerSku']);
        }

        return array_map(fn (array $calculation) => $calculation['locations'], $calculations);
    }

    private function calculateOrgStock(OrgStock $orgStock, Carbon $date): array
    {
        $exchangeRate = GetCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency);

        $costPerSku = $this->getLppPerSku($orgStock, $date);
        $valuation  = $this->getValuationPerSku($orgStock, $date);
        $wacPerSku  = $valuation['wac'];
        $fifoPerSku = $valuation['fifo'];

        $orgStockLocationData = [];
        foreach ($this->locationOrgStocks($orgStock) as $locationOrgStock) {
            $quantity               = $locationOrgStock->quantity;
            $orgStockLocationData[] = [
                'location_id'          => $locationOrgStock->location_id,
                'quantity'             => $quantity,
                'org_stock_lpp_value'  => $quantity * $costPerSku,
                'grp_stock_lpp_value'  => $quantity * $costPerSku * $exchangeRate,
                'org_stock_wac_value'  => $wacPerSku === null ? null : $quantity * $wacPerSku,
                'grp_stock_wac_value'  => $wacPerSku === null ? null : $quantity * $wacPerSku * $exchangeRate,
                'org_stock_fifo_value' => $fifoPerSku === null ? null : $quantity * $fifoPerSku,
                'grp_stock_fifo_value' => $fifoPerSku === null ? null : $quantity * $fifoPerSku * $exchangeRate,
            ];
        }

        return [
            'locations'  => $orgStockLocationData,
            'costPerSku' => $costPerSku,
            'wacPerSku'  => $wacPerSku,
            'fifoPerSku' => $fifoPerSku,
        ];
    }

    private function locationOrgStocks(OrgStock $orgStock): Collection
    {
        if ($orgStock->relationLoaded('locationOrgStocks')) {
            return $orgStock->locationOrgStocks;
        }

        return LocationOrgStock::where('org_stock_id', $orgStock->id)->orderBy('id')->get(['location_id', 'quantity']);
    }

    /**
     * @param Collection<int, OrgStock> $orgStocks
     */
    private function persistCalculations(Carbon $date, Collection $orgStocks, array $calculations): void
    {
        $day         = $date->format('Y-m-d');
        $orgStockIds = $orgStocks->pluck('id')->all();

        $lastSoldDates    = $this->lastSoldDates($orgStockIds, $date);
        $firstAssociates  = $this->firstAssociateDates($orgStockIds);
        $pickedInLastYear = $this->pickedQuantitiesInLastYear($orgStockIds, $date);

        $organisationStockHistories = [];
        $hydratorDelays             = [];
        $emptyOrgStockIds           = [];
        $orgStockHistoryRows        = [];
        foreach ($orgStocks as $orgStock) {
            $calculation = $calculations[$orgStock->id];

            if (empty($calculation['locations'])) {
                $emptyOrgStockIds[]                                 = $orgStock->id;
                $hydratorDelays[$orgStock->organisation_id] ??= $this->hydrateDelay;
            }

            $lastSoldDate = isset($lastSoldDates[$orgStock->id]) ? Carbon::parse($lastSoldDates[$orgStock->id]) : null;
            $values       = $this->orgStockHistoryValues(
                $date,
                $calculation['locations'],
                $calculation['costPerSku'],
                $lastSoldDate,
                $calculation['wacPerSku'],
                $calculation['fifoPerSku'],
                fn (float $quantity) => $this->nonMovingOneYearFrom($date, $quantity, $firstAssociates[$orgStock->id] ?? null, (float)($pickedInLastYear[$orgStock->id] ?? 0))
            );

            $organisationStockHistories[$orgStock->organisation_id] ??= $this->organisationStockHistoryForDate($orgStock, $date);

            $orgStockHistoryRows[] = [
                'date'                          => $day,
                'organisation_id'               => $orgStock->organisation_id,
                'org_stock_id'                  => $orgStock->id,
                'organisation_stock_history_id' => $organisationStockHistories[$orgStock->organisation_id]->id,
            ] + $values;
        }

        if ($emptyOrgStockIds) {
            LocationOrgStockHistory::where('date', $day)->whereIn('org_stock_id', $emptyOrgStockIds)->delete();
        }

        OrgStockHistory::upsert(
            $orgStockHistoryRows,
            ['date', 'organisation_id', 'org_stock_id'],
            array_keys(collect($orgStockHistoryRows[0])->except(['date', 'organisation_id', 'org_stock_id'])->all())
        );

        $orgStockHistoryIds = OrgStockHistory::where('date', $day)
            ->whereIn('organisation_id', array_keys($organisationStockHistories))
            ->whereIn('org_stock_id', $orgStockIds)
            ->pluck('id', 'org_stock_id');

        $locationOrgStockHistoryRows = [];
        foreach ($orgStocks as $orgStock) {
            $calculation = $calculations[$orgStock->id];
            foreach ($calculation['locations'] as $orgStockLocation) {
                $locationOrgStockHistoryRows[] = [
                    'date'                          => $day,
                    'org_stock_id'                  => $orgStock->id,
                    'location_id'                   => $orgStockLocation['location_id'],
                    'org_stock_history_id'          => $orgStockHistoryIds[$orgStock->id],
                    'organisation_stock_history_id' => $organisationStockHistories[$orgStock->organisation_id]->id,
                ] + $this->locationOrgStockHistoryValues($orgStockLocation, $calculation['costPerSku'], $calculation['wacPerSku'], $calculation['fifoPerSku']);
            }
        }

        if ($locationOrgStockHistoryRows) {
            LocationOrgStockHistory::upsert(
                $locationOrgStockHistoryRows,
                ['date', 'org_stock_id', 'location_id'],
                array_keys(collect($locationOrgStockHistoryRows[0])->except(['date', 'org_stock_id', 'location_id'])->all())
            );
        }

        foreach ($organisationStockHistories as $organisationId => $organisationStockHistory) {
            OrganisationStockHistoryHydrateFromOrgStockHistories::dispatch($organisationStockHistory->id)->delay($hydratorDelays[$organisationId] ?? 30);
        }
    }

    private function updateOrgStockCosts(OrgStock $orgStock, float $costPerSku, ?float $fifoPerSku): void
    {
        $officialPerSku = $fifoPerSku > 0 ? $fifoPerSku : $costPerSku;

        $costFields = [];
        if ($officialPerSku > 0) {
            $costFields['sku_value'] = $officialPerSku;
        }
        if ($costPerSku > 0) {
            $costFields['lpp_per_sku'] = $costPerSku;
        }
        if ($costFields) {
            $orgStock->update($costFields);
        }

        OrgStockHydrateValueInLocations::run($orgStock);
        OrgStockHydrateStockValue::run($orgStock);
    }

    public function getLppPerSku(OrgStock $orgStock, Carbon $date): float
    {
        $purchases = $this->preloaded['purchases'][$orgStock->id] ?? null;
        if ($purchases === null) {
            return $this->queryLppPerSku($orgStock, $date);
        }

        $endOfDate = $date->copy()->endOfDay();
        $before    = [];
        $after     = [];
        foreach ($purchases as $purchase) {
            if ($purchase->date->lte($endOfDate)) {
                $before[] = $purchase;
            } else {
                $after[] = $purchase;
            }
        }

        return $this->firstPurchaseCostPerSku(array_slice(array_reverse($before), 0, 20))
            ?? $this->firstPurchaseCostPerSku(array_slice($after, 0, 20))
            ?? (float)($orgStock->current_supplier_sku_cost ?? 0);
    }

    protected function openingOnHand(OrgStock $orgStock, Carbon $valuationStartDate): float
    {
        if ($this->preloaded === null) {
            return $this->queryOpeningOnHand($orgStock, $valuationStartDate);
        }

        return (float)($this->preloaded['openingOnHand'][$orgStock->id] ?? 0);
    }

    /**
     * @return array<int, list<object>>
     */
    private function preloadPurchases(array $orgStockIds): array
    {
        $purchases = array_fill_keys($orgStockIds, []);

        OrgStockMovement::on('aiku_no_sticky')->toBase()
            ->select(['org_stock_id', 'date', 'cost_per_sku', 'org_amount', 'quantity'])
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->orderBy('org_stock_id')->orderBy('date')->orderBy('id')
            ->get()
            ->each(function (object $purchase) use (&$purchases) {
                $purchase->date                       = Carbon::parse($purchase->date);
                $purchases[$purchase->org_stock_id][] = $purchase;
            });

        return $purchases;
    }

    /**
     * @param Collection<int, OrgStock> $orgStocks
     *
     * @return array<int, float>
     */
    private function preloadOpeningOnHand(Collection $orgStocks): array
    {
        $openingOnHand = [];
        foreach ($orgStocks->groupBy(fn (OrgStock $orgStock) => $orgStock->organisation->wac_calculations_start_date) as $wacStartDate => $sameStartOrgStocks) {
            if (!$wacStartDate) {
                continue;
            }

            $openingOnHand += OrgStockMovement::on('aiku_no_sticky')->toBase()
                ->whereIn('org_stock_id', $sameStartOrgStocks->pluck('id')->all())
                ->where('date', '<', Carbon::parse($wacStartDate)->startOfDay()->format('Y-m-d H:i:s.u'))
                ->whereNot(fn ($query) => $query->where('type', OrgStockMovementTypeEnum::AUDIT->value)->whereNotNull('source_id'))
                ->groupBy('org_stock_id')
                ->selectRaw('org_stock_id, sum(quantity) as quantity')
                ->pluck('quantity', 'org_stock_id')
                ->all();
        }

        return $openingOnHand;
    }

    private function lastSoldDates(array $orgStockIds, Carbon $date): array
    {
        return OrgStockMovement::on('aiku_no_sticky')->toBase()
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('type', OrgStockMovementTypeEnum::PICKED->value)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->groupBy('org_stock_id')
            ->selectRaw('org_stock_id, max(date) as date')
            ->pluck('date', 'org_stock_id')
            ->all();
    }

    private function firstAssociateDates(array $orgStockIds): array
    {
        return OrgStockMovement::on('aiku_no_sticky')->toBase()
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('type', OrgStockMovementTypeEnum::ASSOCIATE->value)
            ->groupBy('org_stock_id')
            ->selectRaw('org_stock_id, min(date) as date')
            ->pluck('date', 'org_stock_id')
            ->all();
    }

    private function pickedQuantitiesInLastYear(array $orgStockIds, Carbon $date): array
    {
        return OrgStockMovement::on('aiku_no_sticky')->toBase()
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('type', OrgStockMovementTypeEnum::PICKED->value)
            ->where('date', '>=', $date->copy()->subYear()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->groupBy('org_stock_id')
            ->selectRaw('org_stock_id, sum(quantity) as quantity')
            ->pluck('quantity', 'org_stock_id')
            ->all();
    }

    private function nonMovingOneYearFrom(Carbon $date, float $quantityOnDate, ?string $firstAssociateDate, float $pickedQuantity): float
    {
        if ($firstAssociateDate && Carbon::parse($firstAssociateDate)->lt($date->copy()->subYear())) {
            return 0;
        }

        return max(0, $quantityOnDate - $pickedQuantity);
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:calculate_current_quantity_on_locations {orgStock : OrgStock ID or slug}';
    }

    public function asCommand(Command $command): int
    {
        $this->debug = true;
        if (is_numeric($command->argument('orgStock'))) {
            $orgStock = OrgStock::where('id', $command->argument('orgStock'))->firstOrFail();
        } else {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
        }

        $command->line("Get Stock of $orgStock->slug  ($orgStock->id) on ".now()->format('Y-m-d'));
        $this->handle($orgStock->id);

        return 0;
    }

}
