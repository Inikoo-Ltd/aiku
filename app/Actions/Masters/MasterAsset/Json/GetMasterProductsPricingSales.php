<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sales figures for the master products pricing tab, for a selectable interval.
 * Reads precomputed time series records: one row for the last complete period of
 * the chosen frequency plus the same period a year earlier for the delta.
 */
class GetMasterProductsPricingSales extends OrgAction
{
    use WithMastersAuthorisation;

    /**
     * 'year' is a rolling window: the last 12 complete months (and the 12 before for the
     * delta), so it is always a full, comparable period no matter the day of the year.
     *
     * @return array{frequency: string, current: array<int, string>, previous_year: array<int, string>}
     */
    public static function periodKeys(string $interval): array
    {
        return match ($interval) {
            'month' => [
                'frequency'     => 'monthly',
                'current'       => [now()->subMonth()->format('Y-m')],
                'previous_year' => [now()->subMonth()->subYear()->format('Y-m')],
            ],
            'quarter' => [
                'frequency'     => 'quarterly',
                'current'       => [now()->subQuarter()->year.' Q'.now()->subQuarter()->quarter],
                'previous_year' => [now()->subQuarter()->subYear()->year.' Q'.now()->subQuarter()->quarter],
            ],
            default => [
                'frequency'     => 'monthly',
                'current'       => collect(range(1, 12))->map(fn (int $monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m'))->all(),
                'previous_year' => collect(range(13, 24))->map(fn (int $monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m'))->all(),
            ],
        };
    }

    public function handle(array $masterAssetIDs, string $interval): array
    {
        $periods          = static::periodKeys($interval);
        $currentBindings  = $periods['current'];
        $previousBindings = $periods['previous_year'];
        $currentIn        = implode(',', array_fill(0, count($currentBindings), '?'));
        $previousIn       = implode(',', array_fill(0, count($previousBindings), '?'));

        return DB::table('master_asset_time_series as ts')
            ->join('master_asset_time_series_records as r', function ($join) use ($currentBindings, $previousBindings) {
                $join->on('r.master_asset_time_series_id', '=', 'ts.id')
                    ->whereIn('r.period', array_merge($currentBindings, $previousBindings));
            })
            ->where('ts.frequency', $periods['frequency'])
            ->whereIn('ts.master_asset_id', $masterAssetIDs)
            ->groupBy('ts.master_asset_id')
            ->select('ts.master_asset_id')
            ->selectRaw("sum(case when r.period in ($currentIn) then r.sales_grp_currency_external else 0 end) as sales", $currentBindings)
            ->selectRaw("sum(case when r.period in ($currentIn) then r.sold else 0 end) as sold", $currentBindings)
            ->selectRaw("sum(case when r.period in ($currentIn) then r.customers_invoiced else 0 end) as customers", $currentBindings)
            ->selectRaw("sum(case when r.period in ($previousIn) then r.sales_grp_currency_external else 0 end) as sales_ly", $previousBindings)
            ->get()
            ->keyBy('master_asset_id')
            ->toArray();
    }

    public function rules(): array
    {
        return [
            'interval' => ['required', 'in:month,quarter,year'],
            'ids'      => ['required', 'array', 'max:200'],
            'ids.*'    => ['integer'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->validatedData['ids'], $this->validatedData['interval']);
    }
}
