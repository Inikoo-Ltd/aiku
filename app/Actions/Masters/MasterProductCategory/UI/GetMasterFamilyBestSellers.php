<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Http\Resources\Masters\MasterFamilyBestSellerResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterFamilyBestSellers
{
    use AsObject;

    private const NUMBER_BEST_SELLERS = 3;

    private const NUMBER_MONTHS = 3;

    public function handle(MasterProductCategory $masterFamily): array
    {
        return [
            'products'         => MasterFamilyBestSellerResource::collection($this->getBestSellers($masterFamily))->resolve(),
            'currency'         => $masterFamily->group->currency->code,
            'major_currencies' => $this->getMajorCurrencies($masterFamily),
            'route'            => [
                'name'       => 'grp.masters.master_shops.show.master_families.master_products.show',
                'parameters' => [
                    'masterShop'   => $masterFamily->masterShop->slug,
                    'masterFamily' => $masterFamily->slug,
                ]
            ],
        ];
    }

    private function getMajorCurrencies(MasterProductCategory $masterFamily): array
    {
        return GetMasterShopCurrenciesRate::run($masterFamily->masterShop)
            ->filter(fn (array $currency) => $currency['is_major'])
            ->values()
            ->all();
    }

    private function getBestSellers(MasterProductCategory $masterFamily): Collection
    {
        if ($masterFamily->type !== MasterProductCategoryTypeEnum::FAMILY) {
            return collect();
        }

        $salesSum = 'SUM(COALESCE(master_asset_time_series_records.sales_grp_currency_external, 0) + COALESCE(master_asset_time_series_records.sales_grp_currency_internal, 0))';

        return MasterAsset::query()
            ->join('master_asset_time_series', 'master_asset_time_series.master_asset_id', '=', 'master_assets.id')
            ->join('master_asset_time_series_records', 'master_asset_time_series_records.master_asset_time_series_id', '=', 'master_asset_time_series.id')
            ->where('master_assets.master_family_id', $masterFamily->id)
            ->where('master_assets.status', true)
            ->where('master_asset_time_series_records.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
            ->where('master_asset_time_series_records.period', '>=', now()->subMonths(self::NUMBER_MONTHS)->format('Y-m'))
            ->groupBy('master_assets.id')
            ->havingRaw($salesSum.' > 0')
            ->select([
                'master_assets.id',
                'master_assets.slug',
                'master_assets.code',
                'master_assets.name',
                'master_assets.web_images',
                'master_assets.master_prices',
                DB::raw($salesSum.' as sales'),
            ])
            ->orderByDesc('sales')
            ->limit(self::NUMBER_BEST_SELLERS)
            ->get();
    }
}
