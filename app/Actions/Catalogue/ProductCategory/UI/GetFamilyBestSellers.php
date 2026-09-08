<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Catalogue\FamilyBestSellerResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFamilyBestSellers
{
    use AsObject;

    private const NUMBER_BEST_SELLERS = 3;

    private const NUMBER_MONTHS = 3;

    public function handle(ProductCategory $family): array
    {
        return [
            'products' => FamilyBestSellerResource::collection($this->getBestSellers($family))->resolve(),
            'currency' => $family->shop->currency->code,
            'route'    => $this->getProductRoute($family),
        ];
    }

    /**
     * @return array{name: string, parameters: array<string, string>}
     */
    private function getProductRoute(ProductCategory $family): array
    {
        $routeName = request()->route()?->getName();

        return match ($routeName) {
            'grp.org.shops.show.catalogue.departments.show.families.show' => [
                'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.products.show',
                'parameters' => [
                    'organisation' => $family->organisation->slug,
                    'shop'         => $family->shop->slug,
                    'department'   => $family->department->slug,
                    'family'       => $family->slug,
                ],
            ],
            'grp.org.shops.show.catalogue.sub_departments.show.families.show' => [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show',
                'parameters' => [
                    'organisation'  => $family->organisation->slug,
                    'shop'          => $family->shop->slug,
                    'subDepartment' => $family->subDepartment->slug,
                    'family'        => $family->slug,
                ],
            ],
            default => [
                'name'       => 'grp.org.shops.show.catalogue.families.show.products.show',
                'parameters' => [
                    'organisation' => $family->organisation->slug,
                    'shop'         => $family->shop->slug,
                    'family'       => $family->slug,
                ],
            ],
        };
    }

    private function getBestSellers(ProductCategory $family): Collection
    {
        if ($family->type !== ProductCategoryTypeEnum::FAMILY) {
            return collect();
        }

        $salesSum = 'SUM(COALESCE(asset_time_series_records.sales_external, 0) + COALESCE(asset_time_series_records.sales_internal, 0))';

        return Product::query()
            ->join('assets', 'assets.id', '=', 'products.asset_id')
            ->join('asset_time_series', 'asset_time_series.asset_id', '=', 'assets.id')
            ->join('asset_time_series_records', 'asset_time_series_records.asset_time_series_id', '=', 'asset_time_series.id')
            ->where('products.family_id', $family->id)
            ->where('products.is_main', true)
            ->whereNull('products.exclusive_for_customer_id')
            ->where('asset_time_series_records.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
            ->where('asset_time_series_records.period', '>=', now()->subMonths(self::NUMBER_MONTHS)->format('Y-m'))
            ->groupBy('products.id')
            ->havingRaw($salesSum.' > 0')
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.web_images',
                'products.price',
                DB::raw($salesSum.' as sales'),
            ])
            ->orderByDesc('sales')
            ->limit(self::NUMBER_BEST_SELLERS)
            ->get();
    }
}
