<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 14 Feb 2024 16:17:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Fulfilment\WithPalletReturnSubNavigation;
use App\Actions\Helpers\Upload\UI\IndexPalletReturnItemUploads;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\UI\Fulfilment\PalletReturnsTabsEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Http\Resources\Helpers\PalletReturnItemUploadsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
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

class IndexPalletReturns extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithPalletReturnSubNavigation;
    use WithFulfilmentShopAuthorisation;


    private Fulfilment|FulfilmentCustomer|RecurringBill $parent;
    private ?string $restriction = null;



    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'all';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentConfirmed(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'confirmed';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentNew(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'new';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentPicking(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'picking';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentPicked(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'picked';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentDispatched(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'dispatched';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCancelled(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->restriction = 'cancelled';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilmentCustomer;
        $this->restriction = 'all';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilmentCustomer, PalletReturnsTabsEnum::RETURNS->value);
    }


    protected function getElementGroups(Organisation|FulfilmentCustomer|Fulfilment|PalletDelivery|PalletReturn|RecurringBill $parent): array
    {
        $allowedStates = PalletReturnStateEnum::labels(forElements: true);
        $countStates   = PalletReturnStateEnum::count($parent, forElements: true);

        if ($this->restriction === 'new') {
            $allowedStates = array_filter($allowedStates, fn ($key) => in_array($key, ['in_process', 'submitted', 'confirmed']), ARRAY_FILTER_USE_KEY);
            $countStates   = array_filter($countStates, fn ($key) => in_array($key, ['in_process', 'submitted', 'confirmed']), ARRAY_FILTER_USE_KEY);
        }

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive($allowedStates, $countStates),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('pallet_returns.state', $elements);
                }
            ],
        ];
    }

    public function handle(Fulfilment|FulfilmentCustomer|RecurringBill $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reference', $value)
                    ->orWhereStartWith('customer_reference', $value)
                    ->orWhereStartWith('slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturn::class);
        $queryBuilder->leftJoin('pallet_return_stats', 'pallet_return_stats.pallet_return_id', '=', 'pallet_returns.id');
        $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'pallet_returns.currency_id');

        if ($parent instanceof Fulfilment) {
            $queryBuilder->where('pallet_returns.fulfilment_id', $parent->id);
        } elseif ($parent instanceof RecurringBill) {
            $queryBuilder->where('pallet_returns.recurring_bill_id', $parent->id);
        } else {
            $queryBuilder->where('pallet_returns.fulfilment_customer_id', $parent->id);
        }


        if ($this->restriction == 'all' || $this->restriction == 'new') {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        if ($this->restriction) {
            switch ($this->restriction) {
                case 'dispatched':
                    $queryBuilder->where('state', PalletReturnStateEnum::DISPATCHED);
                    break;
                case 'confirmed':
                    $queryBuilder->where('state', PalletReturnStateEnum::CONFIRMED);
                    break;
                case 'picking':
                    $queryBuilder->where('state', PalletReturnStateEnum::PICKING);
                    break;
                case 'picked':
                    $queryBuilder->where('state', PalletReturnStateEnum::PICKED);
                    break;
                case 'cancelled':
                    $queryBuilder->where('state', PalletReturnStateEnum::CANCEL);
                    break;
                case 'new':
                    $queryBuilder->whereIn('state', [PalletReturnStateEnum::CONFIRMED, PalletReturnStateEnum::SUBMITTED, PalletReturnStateEnum::IN_PROCESS]);
                    break;
                case 'handling':
                    $queryBuilder->whereIn(
                        'state',
                        [
                            PalletReturnStateEnum::CONFIRMED,
                            PalletReturnStateEnum::PICKING,
                            PalletReturnStateEnum::PICKED
                        ]
                    );
            }
        }

        $queryBuilder->defaultSort('-date');

        return $queryBuilder
            ->select('pallet_returns.id', 'state', 'slug', 'reference', 'customer_reference', 'number_pallets', 'number_services', 'number_physical_goods', 'date', 'dispatched_at', 'type', 'total_amount', 'currencies.code as currency_code')
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets', 'date', 'state'])
            ->allowedFilters([$globalSearch, 'type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment|FulfilmentCustomer|RecurringBill $parent, ?array $modelOperations = null, $prefix = null, string $restriction = 'all'): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $restriction) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($restriction == 'all' || ($parent instanceof Fulfilment && $restriction == 'new')) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title' => __('No pallet returns found for this shop'),
                            'count' => $parent->stats->number_pallet_returns
                        ],
                        'RecurringBill' => [
                            'title'       => __('No pallet returns found for this recurring bill'),
                            'description' => __('This recurring bill has no any pallet returns yet'),
                            'count'       => $parent->stats->number_pallet_returns
                        ],
                        'FulfilmentCustomer' => [
                            'title'       => __('No pallet returns found for this customer'),
                            'description' => __('This customer has not received any pallet returns yet'),
                            'count'       => $parent->number_pallet_returns
                        ]
                    }
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $returns): AnonymousResourceCollection
    {
        return PalletReturnsResource::collection($returns);
    }

    public function htmlResponse(LengthAwarePaginator $returns, ActionRequest $request): Response
    {

        $navigation = PalletReturnsTabsEnum::navigation();

        $subNavigation = [];

        $icon       = ['fal', 'fa-sign-out-alt'];
        $title      = __('returns');
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon          = ['fal', 'fa-user'];
            $title         = $this->parent->customer->name;
            $iconRight     = [
                'icon' => 'fal fa-sign-out-alt',
            ];
            $afterTitle    = [

                'label' => __('returns')
            ];
        } elseif ($this->parent instanceof Fulfilment) {
            $model         = __('Operations');
            $subNavigation = $this->getPalletReturnSubNavigation($this->parent, $request);
        }


        $actions = [];


        if ($this->parent->number_pallets_status_storing) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'tooltip'     => $this->parent->items_storage ? __('Create new return (whole pallet)') : __('Create new return'),
                'label'       => $this->parent->items_storage ? __('Return (whole pallet) ') : __('Return'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.store',
                    'parameters' => [$this->parent->id]
                ]
            ];
        }
        if ($this->parent->items_storage) {
            if ($this->parent->number_pallets_with_stored_items_state_storing > 0) {
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
            }
        }

        return Inertia::render(
            'Org/Fulfilment/PalletReturns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallet returns'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => PalletReturnsResource::collection($returns),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                PalletReturnsTabsEnum::RETURNS->value => $this->tab == PalletReturnsTabsEnum::RETURNS->value ?
                    fn () => PalletReturnsResource::collection($returns)
                    : Inertia::lazy(fn () => PalletReturnsResource::collection($returns)),

                PalletReturnsTabsEnum::UPLOADS->value => $this->tab == PalletReturnsTabsEnum::UPLOADS->value ?
                    fn () => PalletReturnItemUploadsResource::collection(IndexPalletReturnItemUploads::run($this->parent, PalletReturnsTabsEnum::UPLOADS->value))
                    : Inertia::lazy(fn () => PalletReturnItemUploadsResource::collection(IndexPalletReturnItemUploads::run($this->parent, PalletReturnsTabsEnum::UPLOADS->value))),

            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: PalletReturnsTabsEnum::RETURNS->value, restriction: $this->restriction))
            ->table(IndexPalletReturnItemUploads::make()->tableStructure(prefix: PalletReturnsTabsEnum::UPLOADS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Returns'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {


            'grp.org.fulfilments.show.crm.customers.show.pallet_returns.index' => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                    ]
                )
            ),
            'grp.org.fulfilments.show.operations.pallet-returns.index',
            'grp.org.fulfilments.show.operations.pallet-returns.confirmed.index',
            'grp.org.fulfilments.show.operations.pallet-returns.picking.index',
            'grp.org.fulfilments.show.operations.pallet-returns.dispatched.index',
            'grp.org.fulfilments.show.operations.pallet-returns.cancelled.index',
            'grp.org.fulfilments.show.operations.pallet-returns.new.index',
            'grp.org.fulfilments.show.operations.pallet-returns.picked.index' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                    ]
                )
            ),
        };
    }
}
