<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerPalletsTabsEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexPalletsInCustomer extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;


    private FulfilmentCustomer $fulfilmentCustomer;
    private FulfilmentCustomer $parent;


    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value)
                    ->orWhereWith('pallets.notes', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);


        $query->where('fulfilment_customer_id', $fulfilmentCustomer->id);


        if ($prefix == FulfilmentCustomerPalletsTabsEnum::STORING->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::STORING, PalletStatusEnum::RETURNING]);
        } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::INCOMING->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::IN_PROCESS]);
        } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::INCIDENT->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::INCIDENT]);
        } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::RETURNED->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::RETURNED]);
        }


        $query->leftjoin('locations', 'pallets.location_id', '=', 'locations.id');

        $query->defaultSort('pallets.id')
            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallets.state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.received_at',
                'pallets.location_id',
                'locations.code as location_code',
                'locations.slug as location_slug',
                'pallets.fulfilment_customer_id',
                'pallets.dispatched_at',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id'
            );


        return $query->allowedSorts(['customer_reference', 'reference', 'dispatched_at', 'fulfilment_customer_name', 'location_code'])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(FulfilmentCustomer $fulfilmentCustomer, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $fulfilmentCustomer) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $count = 0;

            if ($prefix == FulfilmentCustomerPalletsTabsEnum::STORING->value) {
                $count = $fulfilmentCustomer->number_pallets_state_storing;
            } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::INCOMING) {
                $count = $fulfilmentCustomer->number_pallets_state_in_process;
            } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::INCIDENT) {
                $count = $fulfilmentCustomer->number_pallets_status_incident;
            } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::RETURNED) {
                $count = $fulfilmentCustomer->number_pallets_status_returned;
            } elseif ($prefix == FulfilmentCustomerPalletsTabsEnum::ALL) {
                $count = $fulfilmentCustomer->number_pallets;
            }


            $emptyStateData = [
                'icons'       => ['fal fa-pallet'],
                'title'       => __('No pallets found'),
                'count'       => $count,
                'description' => __("This customer don't have any pallets")
            ];


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'type_icon', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), canBeHidden: false, sortable: true, searchable: true);
            if ($prefix == FulfilmentCustomerPalletsTabsEnum::RETURNED->value) {
                $table->column(key: 'dispatched_at', label: __('dispatched'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'location_code', label: __('location'), canBeHidden: false, sortable: true, searchable: true);
            }
            if ($this->fulfilmentCustomer->items_storage) {
                $table->column(key: 'stored_items', label: __("Customer's SKUs"), canBeHidden: false);
            }

            if ($prefix == FulfilmentCustomerPalletsTabsEnum::STORING->value) {
                $table->column(key: 'location_code', label: __('location'), canBeHidden: false, sortable: true, searchable: true);
            }


            $table->defaultSort('reference');
        };
    }


    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }


    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->fulfilmentCustomer, $request);

        $icon       = ['fal', 'fa-user'];
        $title      = $this->fulfilmentCustomer->customer->name;
        $iconRight  = [
            'icon' => 'fal fa-pallet',
        ];
        $afterTitle = [

            'label' => __('pallets')
        ];

        $actions = [];

        if ($this->parent->number_pallets_status_storing) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'tooltip'     => $this->parent->items_storage ? __('Create new return (whole pallet)') : __('Create new return'),
                'label'       => $this->parent->items_storage ? __('Return (whole pallet)') : __('Return'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.store',
                    'parameters' => [$this->parent->id]
                ]
            ];
        }
        if ($this->parent->items_storage) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'tooltip'     => __('Create new return (Customer SKUs)'),
                'label'       => __('Return (Customer SKUs)'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return-stored-items.store',
                    'parameters' => [$this->parent->id]
                ]
            ];
            if ($this->parent->number_pallets_state_storing > 0) {
                $openStoredItemAudit = $this->parent->storedItemAudits()->where('state', StoredItemAuditStateEnum::IN_PROCESS)->first();

                if ($openStoredItemAudit) {
                    $actions[] = [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'tooltip' => __("Continue customer's SKUs audit"),
                        'label'   => __("Continue customer's SKUs audit"),
                        'route'   => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show',
                            'parameters' => array_merge($request->route()->originalParameters(), ['storedItemAudit' => $openStoredItemAudit->slug])
                        ]
                    ];
                } else {
                    $actions[] = [
                        'type'    => 'button',
                        'tooltip' => __("Start customer's SKUs audit"),
                        'label'   => __("Start customer's SKUs audit"),
                        'route'   => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.create',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ];
                }
            }
        }


        return Inertia::render(
            'Org/Fulfilment/PalletsInCustomer',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('Returned Pallets'),
                'pageHead'    => [
                    'title'      => $title,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,

                    'subNavigation' => $subNavigation,

                    'actions' => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentCustomerPalletsTabsEnum::navigation($this->fulfilmentCustomer),
                ],

                FulfilmentCustomerPalletsTabsEnum::STORING->value => $this->tab == FulfilmentCustomerPalletsTabsEnum::STORING->value ?
                    fn() => PalletsResource::collection($pallets)
                    : Inertia::lazy(fn() => PalletsResource::collection($pallets)),

                FulfilmentCustomerPalletsTabsEnum::INCOMING->value => $this->tab == FulfilmentCustomerPalletsTabsEnum::INCOMING->value ?
                    fn() => PalletsResource::collection($pallets)
                    : Inertia::lazy(fn() => PalletsResource::collection($pallets)),


                FulfilmentCustomerPalletsTabsEnum::RETURNED->value => $this->tab == FulfilmentCustomerPalletsTabsEnum::RETURNED->value ?
                    fn() => PalletsResource::collection($pallets)
                    : Inertia::lazy(fn() => PalletsResource::collection($pallets)),

                FulfilmentCustomerPalletsTabsEnum::INCIDENT->value => $this->tab == FulfilmentCustomerPalletsTabsEnum::INCIDENT->value ?
                    fn() => PalletsResource::collection($pallets)
                    : Inertia::lazy(fn() => PalletsResource::collection($pallets)),

                FulfilmentCustomerPalletsTabsEnum::ALL->value => $this->tab == FulfilmentCustomerPalletsTabsEnum::ALL->value ?
                    fn() => PalletsResource::collection($pallets)
                    : Inertia::lazy(fn() => PalletsResource::collection($pallets)),


            ]
        )
            ->table($this->tableStructure($this->fulfilmentCustomer, FulfilmentCustomerPalletsTabsEnum::STORING->value))
            ->table($this->tableStructure($this->fulfilmentCustomer, FulfilmentCustomerPalletsTabsEnum::INCOMING->value))
            ->table($this->tableStructure($this->fulfilmentCustomer, FulfilmentCustomerPalletsTabsEnum::RETURNED->value))
            ->table($this->tableStructure($this->fulfilmentCustomer, FulfilmentCustomerPalletsTabsEnum::INCIDENT->value))
            ->table($this->tableStructure($this->fulfilmentCustomer, FulfilmentCustomerPalletsTabsEnum::ALL->value));
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->parent             = $fulfilmentCustomer;// This is needed for authorisation checks
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentCustomerPalletsTabsEnum::values());

        return $this->handle($fulfilmentCustomer, $request->get('tab', FulfilmentCustomerPalletsTabsEnum::STORING->value));
    }


    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Pallets'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
