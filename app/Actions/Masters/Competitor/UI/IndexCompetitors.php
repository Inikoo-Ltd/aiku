<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexCompetitors extends OrgAction
{
    use WithMastersAuthorisation;

    public function handle(MasterShop $masterShop, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Competitor::class)
            ->where('competitors.master_shop_id', $masterShop->id)
            ->leftJoin('currencies', 'currencies.id', '=', 'competitors.currency_id')
            ->select([
                'competitors.id',
                'competitors.name',
                'competitors.website',
                'competitors.sells_to',
                'competitors.status',
                'competitors.last_error',
                'competitors.fetched_at',
                'competitors.number_products',
                'currencies.code as currency_code',
            ])
            ->selectRaw('competitors.username is not null as has_login')
            ->selectRaw('competitors.search_url is not null as has_search')
            ->defaultSort('name')
            ->allowedSorts(['name', 'fetched_at', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState([
                    'title'       => __('No competitors yet'),
                    'description' => __('Add the websites of the competitors whose prices you want to know'),
                ])
                ->column(key: 'status', label: '', type: 'icon')
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'sells_to', label: __('Sells to'))
                ->column(key: 'has_login', label: __('Logs in'))
                ->column(key: 'fetched_at', label: __('Last read'), sortable: true)
                ->column(key: 'number_products', label: __('Products found'), sortable: true, align: 'right')
                ->defaultSort('name');
        };
    }
}
