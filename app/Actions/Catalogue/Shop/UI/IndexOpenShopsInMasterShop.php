<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 10:10:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOpenShopsInMasterShop extends OrgAction
{
    use WithCatalogueAuthorisation;




    public function handle(MasterShop $masterShop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('shops.name', $value)
                    ->orWhereStartWith('shops.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Shop::class);


        $queryBuilder->where('master_shop_id', $masterShop->id);
        $queryBuilder->where('shops.state', ShopStateEnum::OPEN->value);
        $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'shops.currency_id');


        return $queryBuilder
            ->defaultSort('shops.code')
            ->select(['shops.code','shops.organisation_id', 'shops.id', 'shops.name', 'shops.slug', 'shops.type', 'shops.state','currencies.code as currency_code'])
            ->allowedSorts(['code', 'name', 'type', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterShop $masterShop, $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix, $masterShop) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }



            $emptyState = [
                'title'       => __('No shops found'),
                'description' => '',
                'count'       => $masterShop->stats->number_shops,
                'action'      => null

            ];



            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->withEmptyState($emptyState)
                ->column(key: 'state', label: '', canBeHidden: false, type: 'avatar')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

}
