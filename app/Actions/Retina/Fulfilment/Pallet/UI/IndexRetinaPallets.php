<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Pallet\UI;

use App\Actions\Retina\Fulfilment\Pallet\WithRetinaPalletSubNavigation;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\RetinaPalletsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPallets extends RetinaAction
{
    use WithRetinaPalletSubNavigation;

    private string $bucket = '';

    protected function getElementGroups(FulfilmentCustomer $fulfilmentCustomer): array
    {
        return [
            'state' => [
                'label' => __('State'),
                'elements' => array_merge_recursive(
                    PalletStatusEnum::labels($fulfilmentCustomer),
                    PalletStatusEnum::count($fulfilmentCustomer)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('pallets.status', $elements);
                },

            ],

        ];
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);
        $query->leftJoin('rentals', 'pallets.rental_id', 'rentals.id');
        $query->leftJoin('locations', 'pallets.location_id', 'locations.id');

        $query->where('fulfilment_customer_id', $fulfilmentCustomer->id);

        if ($this->bucket == 'storing') {
            $query->whereIn('pallets.status', [PalletStatusEnum::STORING, PalletStatusEnum::RETURNING]);
        } elseif ($this->bucket == 'in_process') {
            $query->whereIn('pallets.status', [PalletStatusEnum::IN_PROCESS]);
        } elseif ($this->bucket == 'incidents') {
            $query->whereIn('pallets.status', [PalletStatusEnum::INCIDENT]);
        } elseif ($this->bucket == 'returned') {
            $query->whereIn('pallets.status', [PalletStatusEnum::RETURNED]);
        }

        foreach ($this->getElementGroups($fulfilmentCustomer) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $query->defaultSort('id')
            ->select('pallets.*', 'rentals.code as rental_code', 'rentals.name as rental_name', 'locations.code as location_code')
            ->allowedSorts(['customer_reference', 'reference', 'state', 'rental_code', 'location_code'])
            ->allowedFilters([$globalSearch, 'customer_reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(FulfilmentCustomer $fulfilmentCustomer, $prefix = null, $modelOperations = [], string $bucket = ''): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $fulfilmentCustomer, $bucket) {
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

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => '',
                'count' => $fulfilmentCustomer->pallets()->count(),
            ];

            if ($fulfilmentCustomer instanceof FulfilmentCustomer) {
                $emptyStateData['description'] = __("You don't have any stored pallets");
            }

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'status', label: __('Status'), sortable: true, type: 'icon');
            $table->column(key: 'reference', label: __('Id'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);

            if ($bucket == 'storing' || $bucket == 'all') {
                $table->column(key: 'location_code', label: __('Location'), canBeHidden: false, sortable: true, searchable: true);
            } elseif ($bucket == 'returned') {
                $table->column(key: 'location_code', label: __('Delivered From'), tooltip: __('The location the pallet was delivered from'), canBeHidden: false, sortable: true, searchable: true);
            } elseif ($bucket == 'incidents') {
                $table->column(key: 'location_code', label: __('Last Known Location'), tooltip: __('The location the pallet was last seen at'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'rental_code', label: __('Rent'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'stored_items', label: 'Stored Items', canBeHidden: false, searchable: true);
            $table->column(key: 'notes', label: __('Notes'), canBeHidden: false, searchable: true)
                ->defaultSort('reference');
        };
    }

    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        $fulfilmentCustomer = $this->fulfilmentCustomer;
        $subNavigation = $this->getPalletSubNavigation($fulfilmentCustomer);

        $actions = [];
        if ($fulfilmentCustomer->pallets_storage) {
            $actions[] = [
                'type' => 'button',
                'style' => 'create',
                'label' => __('New Storage or Service'),
                'route' => [
                    'method' => 'post',
                    'name' => 'retina.models.pallet-delivery.store',
                    'parameters' => [],
                ],
            ];
        }

        if ($fulfilmentCustomer->number_pallets_status_storing) {
            $actions[] =
                [
                    'type' => 'button',
                    'style' => 'create',
                    'label' => __('New Dispatch'),
                    'route' => [
                        'method' => 'post',
                        'name' => 'retina.models.pallet-return.store',
                        'parameters' => [],
                    ],
                ];
        }

        return Inertia::render(
            'Storage/RetinaPallets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                ),
                'title' => __('My Stored Goods'),
                'pageHead' => [
                    'title' => __('my stored goods'),
                    'icon' => ['fal', 'fa-pallet'],
                    'actions' => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'data' => RetinaPalletsResource::collection($pallets),
            ]
        )->table($this->tableStructure($this->customer->fulfilmentCustomer, 'pallets', bucket: $this->bucket));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer, 'pallets');
    }

    public function storing(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'storing';
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer, 'pallets');
    }

    public function inProcess(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer, 'pallets');
    }

    public function returned(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'returned';
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer, 'pallets');
    }

    public function incidents(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'incidents';
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer, 'pallets');
    }

    public function getBreadcrumbs(string $routeName): array
    {
        return match ($routeName) {
            'retina.fulfilment.storage.pallets.index' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallets.index',
                            ],
                            'label' => __('Goods'),
                            'icon' => 'fal fa-bars',
                        ],

                    ],
                ]
            ),
            'retina.fulfilment.storage.pallets.storing_pallets.index' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallets.storing_pallets.index',
                            ],
                            'label' => __('Goods').' ('.__('Storing').')',
                            'icon' => 'fal fa-bars',
                        ],

                    ],
                ]
            ),
            'retina.fulfilment.storage.pallets.returned_pallets.index' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallets.returned_pallets.index',
                            ],
                            'label' => __('Goods').' ('.__('Returned').')',
                            'icon' => 'fal fa-bars',
                        ],

                    ],
                ]
            ),
            'retina.fulfilment.storage.pallets.in_process_pallets.index' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallets.in_process_pallets.index',
                            ],
                            'label' => __('Goods').' ('.__('In Process').')',
                            'icon' => 'fal fa-bars',
                        ],

                    ],
                ]
            ),
            'retina.fulfilment.storage.pallets.incidents_pallets.index' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallets.incidents_pallets.index',
                            ],
                            'label' => __('Goods').' ('.__('Incidents').')',
                            'icon' => 'fal fa-bars',
                        ],

                    ],
                ]
            ),
        };
    }
}
