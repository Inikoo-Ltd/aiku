<?php

/*
 * author Louis Perez
 * created on 13-03-2026-14h-40m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Accounting\CreditTransaction\UI;

use App\Actions\Accounting\CreditTransaction\UI\Traits\WithCreditTransactions;
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

class IndexCreditTransactionsAll extends OrgAction
{
    use WithAccountingSubNavigation;
    use WithAccountingAuthorisation;
    use WithCreditTransactions;

    private Shop|Organisation $parent;

    public function handle(Shop|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(CreditTransaction::class);

        if ($parent instanceof Shop) {
            $query->where('credit_transactions.shop_id', $parent->id);
        } else {
            $query->where('credit_transactions.organisation_id', $parent->id);
        }

        $this->applyBaseJoins($query);

        $query->leftJoin('customers', 'customers.id', 'credit_transactions.customer_id');
        $query->leftJoin('shops', 'shops.id', 'credit_transactions.shop_id');
        $query->leftJoin('organisations', 'organisations.id', 'credit_transactions.organisation_id');

        return $query
            ->orderBy('credit_transactions.date', 'desc')
            ->select(array_merge($this->getBaseColumns(), [
                'customers.slug as customer_slug',
                'customers.reference as customer_ref',
                'shops.slug as shop_slug',
                'organisations.slug as org_slug',
            ]))
            ->allowedSorts(['amount', 'running_amount', 'type', 'created_at', 'payment_reference'])
            ->allowedFilters([$this->getGlobalSearchFilter()])
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

            $this->addBaseTableColumns($table);
        };
    }

    public function jsonResponse(LengthAwarePaginator $creditTransactions): AnonymousResourceCollection
    {
        return CreditTransactionsResource::collection($creditTransactions);
    }

    public function htmlResponse(LengthAwarePaginator $creditTransactions, ActionRequest $request): Response
    {
        $breadCrumbs   = [];
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
                    'title'         => __('Credit Transactions'),
                    'icon'          => 'fal fa-piggy-bank'
                ],
                'data'        => CreditTransactionsResource::collection($creditTransactions)
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
            'grp.org.accounting.credit_transactions.index' =>
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
