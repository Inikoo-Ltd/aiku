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
use App\Actions\Catalogue\HasRentalAgreement;
use App\Actions\Helpers\Upload\UI\IndexPalletUploads;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\UI\Fulfilment\PalletDeliveriesTabsEnum;
use App\Http\Resources\Fulfilment\PalletDeliveriesResource;
use App\Http\Resources\Helpers\PalletUploadsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Models\SysAdmin\Group;
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
    use WithFulfilmentShopAuthorisation;
    use HasRentalAgreement;
    use WithFulfilmentCustomerSubNavigation;

    private ?string $restriction = null;


    private Fulfilment|FulfilmentCustomer|Group|RecurringBill $parent;


    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($fulfilment, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($fulfilmentCustomer, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisationFromGroup($group, $request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($group, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }

    protected function getElementGroups(Organisation|FulfilmentCustomer|Fulfilment|PalletDelivery|PalletReturn|Group|RecurringBill $parent): array
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

    public function handle(Fulfilment|FulfilmentCustomer|Group|RecurringBill $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('pallet_deliveries.reference', $value)
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
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('pallet_deliveries.group_id', $parent->id);
        } elseif ($parent instanceof RecurringBill) {
            $queryBuilder->where('pallet_deliveries.recurring_bill_id', $parent->id);
        } else {
            $queryBuilder->where('pallet_deliveries.fulfilment_customer_id', $parent->id);
        }

        $queryBuilder->leftJoin('organisations', 'pallet_deliveries.organisation_id', '=', 'organisations.id')
            ->leftJoin('fulfilments', 'pallet_deliveries.fulfilment_id', '=', 'fulfilments.id')
            ->leftJoin('shops', 'fulfilments.shop_id', '=', 'shops.id');

        if ($this->restriction) {
            switch ($this->restriction) {
                case 'booked_in':
                    $queryBuilder->where('pallet_deliveries.state', PalletDeliveryStateEnum::BOOKED_IN);
                    break;
                case 'handling':
                    $queryBuilder->whereIn(
                        'pallet_deliveries.state',
                        [
                            PalletDeliveryStateEnum::CONFIRMED,
                            PalletDeliveryStateEnum::RECEIVED,
                            PalletDeliveryStateEnum::BOOKING_IN
                        ]
                    );
            }
        }


        $queryBuilder->select(
            'pallet_deliveries.id',
            'pallet_deliveries.reference',
            'pallet_deliveries.customer_reference',
            'pallet_delivery_stats.number_pallets',
            'pallet_deliveries.estimated_delivery_date',
            'pallet_deliveries.state',
            'pallet_deliveries.slug',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'fulfilments.slug as fulfilment_slug',
        );


        if (!$parent instanceof RecurringBill) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        if ($parent instanceof Fulfilment) {
            $queryBuilder->leftJoin('fulfilment_customers', 'pallet_deliveries.fulfilment_customer_id', '=', 'fulfilment_customers.id')
                ->leftJoin('customers', 'fulfilment_customers.customer_id', '=', 'customers.id')
                ->addSelect('customers.name as customer_name', 'customers.slug as customer_slug');
        }

        return $queryBuilder
            ->defaultSort('reference')
            ->allowedSorts(['reference'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment|FulfilmentCustomer|Group|RecurringBill $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $hasRentalAgreementActive = $parent->rentalAgreement && $parent->rentalAgreement->state == RentalAgreementStateEnum::ACTIVE;
            $hasRentalAgreement       = (bool)$parent->rentalAgreement;

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title' => __('No deliveries found for this shop'),
                            'count' => $parent->stats->number_pallet_deliveries
                        ],
                        'Group' => [
                            'title' => __('No deliveries found for this group'),
                            'count' => $parent->fulfilmentStats->number_pallet_deliveries
                        ],
                        'RecurringBill' => [
                            'title'       => __('No pallet deliveries found for this recurring bill'),
                            'description' => __('This recurring bill has no any pallet deliveries yet'),
                            'count'       => $parent->stats->number_pallet_deliveries
                        ],
                        'FulfilmentCustomer' => [
                            'title'  => __(
                                $hasRentalAgreementActive
                                    ?
                                    __('We did not find any deliveries for this customer')
                                    : (!$hasRentalAgreement ? 'You dont have rental agreement active yet. Please create rental agreement below'
                                    : 'You have rental agreement but its '.$parent->rentalAgreement->state->value)
                            ),
                            'count'  => $parent->number_pallet_deliveries,
                            'action' => $hasRentalAgreementActive
                                ? []
                                : (!$parent->rentalAgreement ? [
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


            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);

            if (!$parent instanceof RecurringBill) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            if ($parent instanceof Fulfilment) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'number_pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'estimated_delivery_date', label: __('estimated delivery date'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $deliveries): AnonymousResourceCollection
    {
        return PalletDeliveriesResource::collection($deliveries);
    }

    public function htmlResponse(LengthAwarePaginator $deliveries, ActionRequest $request): Response
    {
        $navigation = PalletDeliveriesTabsEnum::navigation();
        if ($this->parent instanceof Group) {
            unset($navigation[PalletDeliveriesTabsEnum::UPLOADS->value]);
        }
        $subNavigation = [];

        $icon       = ['fal', 'fa-truck-couch'];
        $title      = __('Fulfilment deliveries');
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon          = ['fal', 'fa-user'];
            $title         = $this->parent->customer->name;
            $iconRight     = [
                'icon' => 'fal fa-truck-couch',
            ];
            $afterTitle    = [

                'label' => __('Deliveries')
            ];
        } elseif ($this->parent instanceof Fulfilment) {
            $model = __('Operations');
        }

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
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
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        match (class_basename($this->parent)) {
                            'FulfilmentCustomer' =>
                            [
                                'type'        => 'button',
                                'style'       => 'create',
                                'tooltip'     => __('Create new delivery'),
                                'label'       => __('Delivery'),
                                'fullLoading' => true,
                                'route'       => [
                                    'method'     => 'post',
                                    'name'       => 'grp.models.fulfilment-customer.pallet-delivery.store',
                                    'parameters' => [$this->parent->id]
                                ]
                            ],
                            default => null
                        }
                    ]
                ],
                'data'        => PalletDeliveriesResource::collection($deliveries),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                PalletDeliveriesTabsEnum::DELIVERIES->value => $this->tab == PalletDeliveriesTabsEnum::DELIVERIES->value ?
                    fn () => PalletDeliveriesResource::collection($deliveries)
                    : Inertia::lazy(fn () => PalletDeliveriesResource::collection($deliveries)),

                PalletDeliveriesTabsEnum::UPLOADS->value => $this->tab == PalletDeliveriesTabsEnum::UPLOADS->value ?
                    fn () => PalletUploadsResource::collection(IndexPalletUploads::run($this->parent, PalletDeliveriesTabsEnum::UPLOADS->value))
                    : Inertia::lazy(fn () => PalletUploadsResource::collection(IndexPalletUploads::run($this->parent, PalletDeliveriesTabsEnum::UPLOADS->value))),
            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: PalletDeliveriesTabsEnum::DELIVERIES->value))
            ->table(IndexPalletUploads::make()->tableStructure(prefix: PalletDeliveriesTabsEnum::UPLOADS->value));
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
            'grp.overview.fulfilment.pallet-deliveries.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.fulfilment.pallet-deliveries.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };
    }
}
