<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Aug 2025 02:50:16 Central Standard Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoicesPerShop extends OrgAction
{
    use AsAction;

    public function handle(Organisation $parent, $prefix = null)
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
        $queryBuilder->leftJoin('shop_ordering_stats', 'shop_id.id', '=', 'shop_ordering_stats.shop_id');
        $queryBuilder->where('shops.type', '!=', ShopTypeEnum::FULFILMENT);

        $queryBuilder->where('shops.organisation_id', $parent->id);





        return $queryBuilder
            ->defaultSort('shops.code')
            ->select([
                'shops.code',
                'shops.id',
                'shops.name',
                'shops.slug',
                'shops.type',
                'shops.state',
                'shop_ordering_stats.number_invoices_type_invoice as number_invoices '

            ])
            ->allowedSorts(['code', 'name', 'type', 'state', 'organisation_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(Group|Organisation $parent, $prefix): \Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }




            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->column(key: 'state', label: '', canBeHidden: false, type: 'avatar');


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


}
