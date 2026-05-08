<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetMasterProductCategoryRecomendation extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(MasterAsset::class)
            ->with(['products.shop'])
            ->leftJoin(
                'master_asset_stats',
                'master_assets.id',
                '=',
                'master_asset_stats.master_asset_id'
            )
            ->leftJoin('groups', 'master_assets.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');

        $selects = [
            'master_assets.id',
            'master_assets.code',
            'master_assets.name',
            'master_assets.slug',
            'master_assets.status',
            'master_assets.price',
            'master_assets.unit',
            'master_assets.units',
            'master_assets.rrp',
            'master_assets.web_images',
            'master_asset_stats.number_current_assets as used_in',
            'currencies.code as currency_code',
            'master_assets.health_rank',
        ];

        $queryBuilder
            ->whereIn('master_assets.id', $masterProductCategory->relatedMasterAssets->pluck('id'))
            ->leftJoin('master_product_category_has_related_assets', function ($join) use ($masterProductCategory) {
                $join->on('master_assets.id', '=', 'master_product_category_has_related_assets.master_asset_id')
                    ->where('master_product_category_has_related_assets.master_product_category_id', $masterProductCategory->id);
            })
            ->select($selects);

        return $queryBuilder
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }
}
