<?php

/*
 * author Louis Perez
 * created on 17-04-2026-15h-34m
 * github: https://github.com/louis-perez
 * copyright 2026
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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletReturnsBacklog extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithPalletReturnSubNavigation;
    use WithFulfilmentShopAuthorisation;

    private Fulfilment|FulfilmentCustomer|RecurringBill $parent;

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent      = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($fulfilment, PalletReturnsTabsEnum::RETURNS->value);
    }

    protected function getElementGroups(Organisation|FulfilmentCustomer|Fulfilment|PalletDelivery|PalletReturn|RecurringBill $parent): array
    {
        $allowedStates = PalletReturnStateEnum::labels(forElements: true);
        $countStates   = PalletReturnStateEnum::count($parent, forElements: true);

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

        $queryBuilder->defaultSort('-date');

        return $queryBuilder
            ->select(
                'pallet_returns.id',
                'state',
                'slug',
                'reference',
                'customer_reference',
                'pallet_return_stats.number_pallets as number_pallets',
                'pallet_return_stats.number_services as number_services',
                'pallet_returns.created_at as date',
                'dispatched_at',
                'type',
                'total_amount',
                'currencies.code as currency_code',
                DB::raw(
                    "(
                    SELECT COUNT(DISTINCT stored_item_id)
                    FROM pallet_return_items
                    WHERE pallet_return_items.pallet_return_id = pallet_returns.id
                ) as unique_stored_item_count"
                )
            )
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets', 'date', 'state', 'unique_stored_item_count'])
            ->allowedFilters([$globalSearch, 'type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment|FulfilmentCustomer|RecurringBill $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
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
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unique_stored_item_count', label: __('stored items'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
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
        $title      = __('Returns');
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;

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
        if ($this->parent->items_storage && $this->parent->number_pallets_with_stored_items_state_storing > 0) {
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

        dd($returns);

        return Inertia::render(
            'Org/Fulfilment/PalletReturns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Pallet Return Backlog'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
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
        )
        ->table($this->tableStructure(parent: $this->parent, prefix: PalletReturnsTabsEnum::RETURNS->value))
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
                        'label' => __('Returns Backlog'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.fulfilments.show.backlogs.pallet-returns-backlog.wholesale' => array_merge(
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
