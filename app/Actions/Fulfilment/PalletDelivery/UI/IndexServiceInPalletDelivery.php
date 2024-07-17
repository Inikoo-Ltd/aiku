<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Jun 2024 22:48:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Service\ServiceStateEnum;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletDelivery;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexServiceInPalletDelivery extends OrgAction
{
    protected function getElementGroups(PalletDelivery $palletDelivery): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ServicestateEnum::labels(),
                    ServicestateEnum::count($palletDelivery->fulfilment->shop)
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
                $query->whereAnyWordStartWith('services.name', $value)
                    ->orWhereStartWith('services.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(FulfilmentTransaction::class);
        $queryBuilder->where('fulfilment_transactions.parent_type', class_basename($palletDelivery));
        $queryBuilder->where('fulfilment_transactions.parent_id', $palletDelivery->id);
        $queryBuilder->where('fulfilment_transactions.type', FulfilmentTransactionTypeEnum::SERVICE->value);
        $queryBuilder->join('assets', 'fulfilment_transactions.asset_id', '=', 'assets.id');
        $queryBuilder->join('historic_assets', 'fulfilment_transactions.historic_asset_id', '=', 'historic_assets.id');

        $queryBuilder->join('services', 'assets.model_id', '=', 'services.id');
        $queryBuilder->join('currencies', 'services.currency_id', '=', 'currencies.id');


        foreach ($this->getElementGroups($palletDelivery) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->defaultSort('services.id')
            ->select([
                'fulfilment_transactions.id',
                'fulfilment_transactions.asset_id',
                'fulfilment_transactions.net_amount',
                'fulfilment_transactions.type as asset_type',
                'fulfilment_transactions.historic_asset_id',
                'services.slug as asset_slug',
                'historic_assets.code as asset_code',
                'historic_assets.name as asset_name',
                'historic_assets.price as asset_price',
                'historic_assets.unit as asset_unit',
                'historic_assets.units as asset_units',

                'fulfilment_transactions.quantity',
                'fulfilment_transactions.parent_id  as pallet_delivery_id',
                'currencies.code as currency_code',
                'fulfilment_transactions.is_auto_assign',


            ]);


        return $queryBuilder->allowedSorts(['id', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(PalletDelivery $palletDelivery, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
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
                        'icons' => ['fal fa-concierge-bell'],
                        'title' => 'No service selected',
                        'count' => $palletDelivery->stats->number_services,
                    ]
                );

            $table
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, className: 'text-right font-mono')
                ->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true, className: 'hello')
                ->defaultSort('id');
        };
    }


    public function jsonResponse(LengthAwarePaginator $services): AnonymousResourceCollection
    {
        return FulfilmentTransactionResource::collection($services);
    }
}
