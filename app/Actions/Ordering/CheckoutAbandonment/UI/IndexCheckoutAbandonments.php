<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\CheckoutAbandonment\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Http\Resources\Ordering\CheckoutAbandonmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\CheckoutAbandonment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCheckoutAbandonments extends OrgAction
{
    use WithCustomerSubNavigation;
    private Group|Organisation|Shop|Customer $parent;

    protected function getElementGroups(Group|Organisation|Shop|Customer $parent): array
    {
        $groupId = $parent instanceof Group ? $parent->id : $parent->group_id;
        $query = CheckoutAbandonment::where('group_id', $groupId);
        if ($parent instanceof Organisation) {
            $query->where('checkout_abandonments.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $query->where('checkout_abandonments.shop_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('checkout_abandonments.customer_id', $parent->id);
        }

        $counts = (clone $query)->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    CheckoutAbandonmentStateEnum::labels(),
                    [
                        'abandoned' => $counts->get('abandoned', 0),
                        'recovered' => $counts->get('recovered', 0),
                    ]
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('checkout_abandonments.state', $elements);
                }
            ],
        ];
    }

    private function getStats(Group|Organisation|Shop|Customer $parent): array
    {
        $query = CheckoutAbandonment::where('checkout_abandonments.group_id', $parent instanceof Group ? $parent->id : $parent->group_id);
        if ($parent instanceof Organisation) {
            $query->where('checkout_abandonments.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $query->where('checkout_abandonments.shop_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('checkout_abandonments.customer_id', $parent->id);
        }

        $agg = $query->selectRaw('state, count(*) as cnt, coalesce(sum(total_amount), 0) as revenue')
            ->groupBy('state')
            ->get()
            ->keyBy('state');

        $abandonedCount = (int) ($agg[CheckoutAbandonmentStateEnum::ABANDONED->value]->cnt ?? 0);
        $recoveredCount = (int) ($agg[CheckoutAbandonmentStateEnum::RECOVERED->value]->cnt ?? 0);
        $total          = $abandonedCount + $recoveredCount;
        $recoveryRate   = $total > 0 ? round($recoveredCount / $total * 100, 1) : 0;

        $currency = match (true) {
            $parent instanceof Shop     => $parent->currency,
            $parent instanceof Customer => $parent->shop->currency,
            default                     => null,
        };

        if ($currency) {
            return [
                ['label' => __('Abandoned'), 'value' => $abandonedCount],
                ['label' => __('Lost revenue'), 'value' => $currency->symbol.number_format((float) ($agg[CheckoutAbandonmentStateEnum::ABANDONED->value]->revenue ?? 0), 2)],
                ['label' => __('Recovery rate'), 'value' => $recoveryRate.'%'],
                ['label' => __('Recovered'), 'value' => $recoveredCount],
                ['label' => __('Recovered revenue'), 'value' => $currency->symbol.number_format((float) ($agg[CheckoutAbandonmentStateEnum::RECOVERED->value]->revenue ?? 0), 2)],
            ];
        }

        return [
            ['label' => __('Abandoned'), 'value' => $abandonedCount],
            ['label' => __('Recovered'), 'value' => $recoveredCount],
            ['label' => __('Recovery rate'), 'value' => $recoveryRate.'%'],
        ];
    }

    public function handle(Group|Organisation|Shop|Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('orders.reference', $value)
                    ->orWhereStartWith('customers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(CheckoutAbandonment::class);
        $query->where('checkout_abandonments.group_id', $parent instanceof Group ? $parent->id : $parent->group_id);

        if ($parent instanceof Organisation) {
            $query->where('checkout_abandonments.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $query->where('checkout_abandonments.shop_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('checkout_abandonments.customer_id', $parent->id);
        }

        $query->leftJoin('orders', 'checkout_abandonments.order_id', '=', 'orders.id');
        $query->leftJoin('customers', 'checkout_abandonments.customer_id', '=', 'customers.id');
        $query->leftJoin('shops', 'checkout_abandonments.shop_id', '=', 'shops.id');
        $query->leftJoin('organisations', 'checkout_abandonments.organisation_id', '=', 'organisations.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('transactions', function ($join) {
            $join->on('orders.id', '=', 'transactions.order_id')
                ->whereNull('transactions.deleted_at');
        });
        $query->selectRaw('count(transactions.id) as number_items');
        $query->groupBy([
            'checkout_abandonments.id',
            'orders.reference',
            'orders.slug',
            'orders.currency_id',
            'customers.name',
            'customers.slug',
            'shops.name',
            'shops.code',
            'shops.slug',
            'organisations.name',
            'organisations.code',
            'organisations.slug',
            'currencies.code',
        ]);

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $query->defaultSort('-checkout_visited_at')
            ->select([
                'checkout_abandonments.id',
                'checkout_abandonments.order_id',
                'checkout_abandonments.state',
                'checkout_abandonments.checkout_visited_at',
                'checkout_abandonments.total_amount',
                'checkout_abandonments.recovered_at',
                'orders.reference',
                'orders.slug as order_slug',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
                'currencies.code as currency_code',
            ])
            ->allowedSorts(['checkout_visited_at', 'total_amount', 'customer_name', 'shop_code', 'organisation_code'])
            ->withBetweenDates(['checkout_visited_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|Shop|Customer $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
                InertiaTable::updateQueryBuilderParameters($prefix);
            }

            $table->betweenDates(['checkout_visited_at']);

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No abandoned checkouts'),
                ]);

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table->column(key: 'state_label', label: __('State'), canBeHidden: false, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_code', label: __('Org'), canBeHidden: false, searchable: true, sortable: true);
            }
            if ($parent instanceof Group || $parent instanceof Organisation) {
                $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, searchable: true, sortable: true);
            }
            if ($parent instanceof Group || $parent instanceof Organisation || $parent instanceof Shop) {
                $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, searchable: true, sortable: true);
            }
            $table->column(key: 'reference', label: __('Order'), canBeHidden: false, searchable: true);
            $table->column(key: 'checkout_visited_at', label: __('Visited checkout'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_items', label: __('Items'), canBeHidden: false);
            $table->column(key: 'total_amount', label: __('Value'), canBeHidden: false, sortable: true, type: 'currency');
        };
    }

    // public function authorize(ActionRequest $request): bool
    // {
    //     if ($this->asAction) {
    //         return true;
    //     }

    //     return match (true) {
    //         $this->parent instanceof Group,
    //         $this->parent instanceof Organisation => $request->user()->authTo('group-overview'),
    //         default => $request->user()->authTo("orders.{$this->shop->id}.view"),
    //     };
    // }

    public function jsonResponse(LengthAwarePaginator $abandonments): AnonymousResourceCollection
    {
        return CheckoutAbandonmentsResource::collection($abandonments);
    }

    public function htmlResponse(LengthAwarePaginator $abandonments, ActionRequest $request): Response
    {
        $title         = __('Abandoned checkouts');
        $subNavigation = null;

        if ($this->parent instanceof Customer) {
            if ($this->parent->is_dropshipping) {
                $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
            } else {
                $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
            }
        }

        return Inertia::render(
            'Ordering/CheckoutAbandonments',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => ['fal', 'fa-shopping-cart'],
                    'title'         => $title,
                    'subNavigation' => $subNavigation,
                ],
                'stats'       => $this->getStats($this->parent),
                'data'        => CheckoutAbandonmentsResource::collection($abandonments),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function inCustomer(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Abandoned checkouts'),
                        'icon'  => 'fal fa-shopping-cart',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.overview.ordering.checkout_abandonments.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(['name' => $routeName, 'parameters' => $routeParameters])
            ),
            'grp.org.overview.checkout_abandonments.index' =>
            array_merge(
                ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(['name' => $routeName, 'parameters' => $routeParameters])
            ),
            'grp.org.shops.show.ordering.checkout_abandonments.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(['name' => $routeName, 'parameters' => $routeParameters])
            ),
            'grp.org.shops.show.crm.customers.show.checkout_abandonments.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(['name' => $routeName, 'parameters' => $routeParameters])
            ),
            default => []
        };
    }
}
