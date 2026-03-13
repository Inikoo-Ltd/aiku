<?php

/*
 * author Louis Perez
 * created on 13-03-2026-14h-40m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Accounting\CreditTransaction\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Http\Resources\Accounting\CreditTransactionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\CreditTransaction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCreditTransactionsAll extends OrgAction
{
    use WithAccountingSubNavigation;
    
    use WithAccountingAuthorisation;

    private Shop|Organisation $parent;

    public function handle(Shop|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                // Cast credit_transactions.amount as char so it is searchable using ILIKE function on PSQL
                $query->whereRaw("credit_transactions.amount::text ILIKE ?", ["%{$value}%"])
                    ->orWhereAnyWordStartWith('credit_transactions.type', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(CreditTransaction::class);

        if ($parent instanceof Shop) {
            $query->where('credit_transactions.shop_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('credit_transactions.organisation_id', $parent->id);
        }

        $query->leftJoin('payments', 'credit_transactions.payment_id', '=', 'payments.id');
        $query->leftJoin('currencies', 'credit_transactions.currency_id', '=', 'currencies.id');
        $query->leftJoin('model_has_payments', function ($join) {
            $join->on('model_has_payments.payment_id', '=', 'payments.id')
                ->where('model_has_payments.model_type', '=', 'Order');
        });
        $query->leftJoin('orders', function ($join) {
            $join->on('model_has_payments.model_id', '=', 'orders.id');
        });
        $query->leftJoin('customers', 'customers.id', 'credit_transactions.customer_id');
        $query->leftJoin('shops', 'shops.id', 'credit_transactions.shop_id');
        $query->leftJoin('organisations', 'organisations.id', 'credit_transactions.organisation_id');

        return $query
            ->orderBy('credit_transactions.date', 'desc')
            ->select([
                'customers.slug as customer_slug',
                'customers.reference as customer_ref',
                'credit_transactions.id',
                'credit_transactions.date as created_at',
                'credit_transactions.type',
                'credit_transactions.amount',
                'credit_transactions.running_amount',
                'payments.reference as payment_reference',
                'payments.id as payment_id',
                'payments.type as payment_type',
                'currencies.code as currency_code',
                'orders.slug as order_slug',
                'orders.reference as order_reference',
                'credit_transactions.notes',
                'shops.slug as shop_slug',
                'organisations.slug as org_slug',
            ])
            ->allowedSorts(['amount', 'running_amount', 'type', 'created_at','payment_reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|Organisation $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }


            $stats     = $parent->stats;
            $noResults = __(":xxParentType has no credit transactions", ['xxParentType' => class_basename($parent)]);


            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_credit_transactions ?? 0
                    ]
                );

            $table->column(key: 'customer_ref', label: __('Customer Ref'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, type: 'date_hm');
            $table->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'payment_reference', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'order_reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'amount', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            $table->column(key: 'running_amount', label: __('Running amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $creditTransactions): AnonymousResourceCollection
    {
        return CreditTransactionsResource::collection($creditTransactions);
    }

    public function htmlResponse(LengthAwarePaginator $creditTransactions, ActionRequest $request): Response
    {
        $breadCrumbs = [];
        $subNavigation = [];

        if ($this->parent instanceof Shop) {
            $breadCrumbs = $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters(),
                "(".__('In Shops').")"
            );

            $subNavigation = $this->getSubNavigationShop($this->parent);
        } elseif ($this->parent instanceof Organisation) {
            $breadCrumbs = $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters(),
                "(".__('In Organisations').")"
            );
        }

        return Inertia::render(
        'Org/Accounting/CreditTransactions',
            [
                'breadcrumbs' => $breadCrumbs,
                'title'       => __('Credit Transactions'),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'title' => __('Credit Transactions'),
                    'icon'  => 'fal fa-piggy-bank'
                ],
                'data' => CreditTransactionsResource::collection($creditTransactions)
            ]
        )->table($this->tableStructure($this->parent));
    }

    
    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix): array
    {
        $headCrumb = function () use ($routeName, $routeParameters, $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Credit Transactions'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.credit_transactions.index'  => 
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb()
            ),
            'grp.org.shops.show.dashboard.payments.accounting.credit_transactions.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }
}
