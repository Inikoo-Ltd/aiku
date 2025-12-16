<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Jun 2024 22:48:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletDelivery;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPhysicalGoodInPalletDelivery extends OrgAction
{
    protected function getElementGroups(PalletDelivery $palletDelivery): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    AssetStateEnum::labels(),
                    AssetStateEnum::count($palletDelivery->fulfilment->shop)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],
        ];
    }

    public function handle(PalletDelivery $palletDelivery, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(FulfilmentTransaction::class);
        $queryBuilder->where('fulfilment_transactions.parent_type', class_basename($palletDelivery));
        $queryBuilder->where('fulfilment_transactions.parent_id', $palletDelivery->id);
        $queryBuilder->where('fulfilment_transactions.type', FulfilmentTransactionTypeEnum::PRODUCT->value);
        $queryBuilder->leftjoin('assets', 'fulfilment_transactions.asset_id', '=', 'assets.id');
        $queryBuilder->leftjoin('historic_assets', 'fulfilment_transactions.historic_asset_id', '=', 'historic_assets.id');
        $queryBuilder->leftjoin('products', 'assets.model_id', '=', 'products.id');
        $queryBuilder->leftjoin('rental_agreement_clauses', 'fulfilment_transactions.rental_agreement_clause_id', '=', 'rental_agreement_clauses.id');

        foreach ($this->getElementGroups($palletDelivery) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->defaultSort('products.id')
            ->select([
                'fulfilment_transactions.id',
                'fulfilment_transactions.asset_id',
                'fulfilment_transactions.net_amount',
                'fulfilment_transactions.historic_asset_id',
                'products.slug as asset_slug',
                'historic_assets.code as code',
                'historic_assets.name as name',
                'historic_assets.price as price',
                'historic_assets.unit as unit',
                'historic_assets.units as units',
                'fulfilment_transactions.quantity',
                'fulfilment_transactions.parent_id  as pallet_delivery_id',
                'fulfilment_transactions.is_auto_assign',
                'rental_agreement_clauses.percentage_off as discount'
            ]);
        $queryBuilder->selectRaw("'{$palletDelivery->currency->code}'  as currency_code");

        return $queryBuilder->allowedSorts([ 'name', 'code','quantity','net_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        PalletDelivery $palletDelivery,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($palletDelivery, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'icons' => ['fal fa-cube'],
                        'title' => __('No physical goods selected'),
                        'count' => $palletDelivery->stats->number_physical_goods,
                    ]
                );

            $table
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, className: 'text-right font-mono')
                ->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true, className: 'hello')
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $physicalGoods): AnonymousResourceCollection
    {
        return FulfilmentTransactionsResource::collection($physicalGoods);
    }
}
