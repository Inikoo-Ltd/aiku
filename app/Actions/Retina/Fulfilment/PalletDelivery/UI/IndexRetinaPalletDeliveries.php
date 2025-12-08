<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletDelivery\UI;

use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\Retina\Helpers\Upload\UI\IndexRetinaPalletUploads;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\UI\Fulfilment\PalletDeliveriesTabsEnum;
use App\Http\Resources\Fulfilment\PalletDeliveriesResource;
use App\Http\Resources\Helpers\PalletUploadsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPalletDeliveries extends RetinaAction
{
    protected function getElementGroups(FulfilmentCustomer $fulfilmentCustomer): array
    {
        return [
            'state' => [
                'label' => __('State'),
                'elements' => array_merge_recursive(
                    PalletDeliveryStateEnum::labels(forElements: true),
                    PalletDeliveryStateEnum::count($fulfilmentCustomer, forElements: true)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('pallet_deliveries.state', $elements);
                },
            ],

        ];
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($this->customer->fulfilmentCustomer, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reference', $value)
                    ->orWhereStartWith('customer_reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletDelivery::class);
        $queryBuilder->where('pallet_deliveries.fulfilment_customer_id', $fulfilmentCustomer->id);
        $queryBuilder->leftJoin('pallet_delivery_stats', 'pallet_deliveries.id', '=', 'pallet_delivery_stats.pallet_delivery_id');
        $queryBuilder->leftJoin('currencies', 'pallet_deliveries.currency_id', '=', 'currencies.id');
        $queryBuilder->leftJoin('organisations', 'pallet_deliveries.organisation_id', '=', 'pallet_deliveries.id')
            ->leftJoin('fulfilments', 'pallet_deliveries.fulfilment_id', '=', 'fulfilments.id')
            ->leftJoin('shops', 'fulfilments.shop_id', '=', 'shops.id');

        foreach ($this->getElementGroups($fulfilmentCustomer) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->select(
            'pallet_deliveries.id',
            'pallet_deliveries.reference',
            'pallet_deliveries.slug',
            'pallet_deliveries.customer_reference',
            'pallet_delivery_stats.number_pallets',
            'pallet_deliveries.estimated_delivery_date',
            'pallet_deliveries.date',
            'pallet_deliveries.state',
            'pallet_deliveries.net_amount',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'fulfilments.slug as fulfilment_slug',
            'currencies.code as currency_code',
        );

        return $queryBuilder
            ->defaultSort('reference')
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets', 'date'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(FulfilmentCustomer $fulfilmentCustomer, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($fulfilmentCustomer, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($fulfilmentCustomer) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('Id'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency')
                ->column(key: 'number_pallets', label: __('total'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $deliveries, ActionRequest $request): Response
    {

        $navigation = PalletDeliveriesTabsEnum::navigation();

        $fulfilmentCustomer = auth()->user()->customer->fulfilmentCustomer;

        $actions = [
            $fulfilmentCustomer->pallets_storage ? [
                'type' => 'button',
                'style' => 'create',
                'label' => __('New Storage or Service'),
                'fullLoading' => true,
                'route' => [
                    'method' => 'post',
                    'name' => 'retina.models.pallet-delivery.store',
                    'parameters' => [],
                ],
            ] : false,
        ];

        return Inertia::render(
            'Storage/RetinaPalletDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('Goods in'),
                'pageHead' => [
                    'model' => __('Storage'),
                    'title' => __('Goods In'),
                    'icon' => [
                        'icon' => ['fal', 'fa-truck'],
                        'title' => __('Delivery'),
                    ],
                    'actions' => array_filter($actions),
                ],
                'data' => PalletDeliveriesResource::collection($deliveries),
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => $navigation,
                ],

                PalletDeliveriesTabsEnum::DELIVERIES->value => $this->tab == PalletDeliveriesTabsEnum::DELIVERIES->value ?
                    fn () => PalletDeliveriesResource::collection($deliveries)
                    : Inertia::lazy(fn () => PalletDeliveriesResource::collection($deliveries)),

                PalletDeliveriesTabsEnum::UPLOADS->value => $this->tab == PalletDeliveriesTabsEnum::UPLOADS->value ?
                    fn () => PalletUploadsResource::collection(IndexRetinaPalletUploads::run($this->webUser, PalletDeliveriesTabsEnum::UPLOADS->value))
                    : Inertia::lazy(fn () => PalletUploadsResource::collection(IndexRetinaPalletUploads::run($this->webUser, PalletDeliveriesTabsEnum::UPLOADS->value))),
            ]
        )->table($this->tableStructure(fulfilmentCustomer: $this->customer->fulfilmentCustomer, prefix: PalletDeliveriesTabsEnum::DELIVERIES->value))
            ->table(IndexRetinaPalletUploads::make()->tableStructure(prefix: PalletDeliveriesTabsEnum::UPLOADS->value));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'retina.fulfilment.storage.pallet_deliveries.index',
                        ],
                        'label' => __('Goods In'),
                        'icon' => 'fal fa-bars',
                    ],

                ],
            ]
        );
    }
}
