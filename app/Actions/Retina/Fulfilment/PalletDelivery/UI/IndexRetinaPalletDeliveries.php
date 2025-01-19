<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletDelivery\UI;

use App\Actions\Catalogue\HasRentalAgreement;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\PalletDeliveriesResource;
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
    use HasRentalAgreement;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer);
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

        return $queryBuilder
            ->defaultSort('reference')
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
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


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('reference number'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __("delivery name"), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_pallets', label: __('total pallets'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Storage/RetinaPalletDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                ),
                'title'       => __('pallet deliveries'),
                'pageHead'    => [
                    'title'   => __('Deliveries'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-truck'],
                        'title' => __('delivery')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New Delivery'),
                            'route' => [
                                'method'     => 'post',
                                'name'       => 'retina.models.pallet-delivery.store',
                                'parameters' => []
                            ]
                        ]
                    ]
                ],
                'data'        => PalletDeliveriesResource::collection($customers),

            ]
        )->table($this->tableStructure($this->customer->fulfilmentCustomer));
    }

    public function getBreadcrumbs(string $routeName): array
    {
        return match ($routeName) {
            'retina.fulfilment.storage.pallet_deliveries.index' =>
            array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallet_deliveries.index',
                            ],
                            'label' => __('Pallet Deliveries'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),
        };
    }
}
