<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\Catalogue\HasRentalAgreement;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasFulfilmentAssetsAuthorisation;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletDeliveries extends OrgAction
{
    use HasFulfilmentAssetsAuthorisation;
    use HasRentalAgreement;
    use WithFulfilmentCustomerSubNavigation;


    private Fulfilment|Warehouse|FulfilmentCustomer $parent;


    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {

        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    protected function getElementGroups(Organisation|FulfilmentCustomer|Fulfilment|Warehouse|PalletDelivery|PalletReturn $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    PalletDeliveryStateEnum::labels(forElements: true),
                    PalletDeliveryStateEnum::count($parent, forElements: true)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('pallet_deliveries.state', $elements);
                }
            ],


        ];
    }

    public function handle(Fulfilment|Warehouse|FulfilmentCustomer $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('pallet_delivery_stats', 'pallet_deliveries.id', '=', 'pallet_delivery_stats.pallet_delivery_id');
        if ($parent instanceof Fulfilment) {
            $queryBuilder->where('pallet_deliveries.fulfilment_id', $parent->id);
        } elseif ($parent instanceof Warehouse) {
            $queryBuilder->where('pallet_deliveries.warehouse_id', $parent->id);
            $queryBuilder->whereNotIn('pallet_deliveries.state', [PalletDeliveryStateEnum::IN_PROCESS, PalletDeliveryStateEnum::SUBMITTED]);
        } else {
            $queryBuilder->where('pallet_deliveries.fulfilment_customer_id', $parent->id);
        }

        $queryBuilder->select(
            'pallet_deliveries.id',
            'pallet_deliveries.reference',
            'pallet_deliveries.customer_reference',
            'pallet_delivery_stats.number_pallets',
            'pallet_deliveries.estimated_delivery_date',
            'pallet_deliveries.state',
            'pallet_deliveries.slug'
        );

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        if($parent instanceof Fulfilment || $parent instanceof Warehouse) {
            $queryBuilder->leftJoin('fulfilment_customers', 'pallet_deliveries.fulfilment_customer_id', '=', 'fulfilment_customers.id')
              ->leftJoin('customers', 'fulfilment_customers.customer_id', '=', 'customers.id')
              ->addSelect('customers.name as customer_name', 'customers.slug as customer_slug');
        }

        return $queryBuilder
            ->defaultSort('pallet_deliveries.reference')
            ->allowedSorts(['pallet_deliveries.reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(Fulfilment|Warehouse|FulfilmentCustomer $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $hasRentalAgreementActive    = $parent->rentalAgreement && $parent->rentalAgreement->state == RentalAgreementStateEnum::ACTIVE;
            $hasRentalAgreement          = (bool) $parent->rentalAgreement;

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title'       => __('No deliveries found for this shop'),
                            'count'       => $parent->stats->number_pallet_deliveries
                        ],
                        'Warehouse' => [
                            'title'       => __('No pallet deliveries found for this warehouse'),
                            'description' => __('This warehouse has not received any pallet deliveries yet'),
                            'count'       => $parent->stats->number_pallet_deliveries
                        ],
                        'FulfilmentCustomer' => [
                            'title'       => __($hasRentalAgreementActive ?
                                __('We did not find any deliveries for this customer')
                                : (!$hasRentalAgreement ? 'You dont have rental agreement active yet. Please create rental agreement below'
                                : 'You have rental agreement but its ' . $parent->rentalAgreement->state->value)),
                            'count'       => $parent->number_pallet_deliveries,
                            'action'      => $hasRentalAgreementActive ? [] : (!$parent->rentalAgreement ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('Create new rental agreement'),
                                'label'   => __('New rental agreement'),
                                'route'   => [
                                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.rental-agreement.create',
                                    'parameters' => array_values(request()->route()->originalParameters())
                                ]
                            ] : false)
                        ]
                    }
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');




            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            if ($parent instanceof Fulfilment) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'estimated_delivery_date', label: __('estimated delivery date'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return PalletDeliveriesResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {

        $subNavigation=[];

        $icon      =['fal', 'fa-truck-couch'];
        $title     =__('deliveries');
        $afterTitle=null;
        $iconRight =null;

        if($this->parent instanceof FulfilmentCustomer) {
            $subNavigation=$this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon         =['fal', 'fa-user'];
            $title        =$this->parent->customer->name;
            $iconRight    =[
                'icon' => 'fal fa-truck-couch',
            ];
            $afterTitle= [

                'label'     => __('Deliveries')
            ];
        }

        if($this->parent instanceof  FulfilmentCustomer) {
            $subNavigation=$this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $action       = [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('Create new pallet delivery'),
                    'label'   => __('Pallet delivery'),
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.fulfilment-customer.pallet-delivery.store',
                        'parameters' => [$this->parent->id]
                    ]
                ]
            ];
        }

        return Inertia::render(
            'Org/Fulfilment/PalletDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallet deliveries'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        match (class_basename($this->parent)) {
                            'FulfilmentCustomer' =>
                                 [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => __('Create new delivery'),
                                    'label'   => __('Delivery'),
                                    'route'   => [
                                        'method'     => 'post',
                                        'name'       => 'grp.models.fulfilment-customer.pallet-delivery.store',
                                        'parameters' => [$this->parent->id]
                                        ]
                                ],
                            default => null
                        }
                    ]
                ],
                'data'        => PalletDeliveriesResource::collection($customers),

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Deliveries'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index' => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                    ]
                )
            ),
            'grp.org.fulfilments.show.operations.pallet-deliveries.index' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                    ]
                )
            ),
            'grp.org.warehouses.show.fulfilment.pallet-deliveries.index' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.fulfilment.pallet-deliveries.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                    ]
                )
            ),
        };
    }
}
