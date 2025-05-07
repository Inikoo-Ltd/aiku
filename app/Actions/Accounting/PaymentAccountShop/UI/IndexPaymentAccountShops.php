<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-16h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Actions\Accounting\PaymentAccount\UI\ShowPaymentAccount;
use App\Actions\Accounting\PaymentAccount\WithPaymentAccountSubNavigation;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Http\Resources\Accounting\PaymentAccountShopsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPaymentAccountShops extends OrgAction
{
    use WithPaymentAccountSubNavigation;
    use WithAccountingSubNavigation;

    private PaymentAccount|Shop|Fulfilment $parent;

    public function handle(PaymentAccount|Shop|Fulfilment $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment_accounts.code', $value)
                    ->orWhereAnyWordStartWith('payment_accounts.name', $value)
                    ->orWhereStartWith('shops.code', $value)
                    ->orWhereAnyWordStartWith('shops.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PaymentAccountShop::class);

        if ($parent instanceof PaymentAccount) {
            $queryBuilder->where('payment_account_shop.payment_account_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('payment_account_shop.shop_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('payment_account_shop.shop_id', $parent->shop->id);
        }

        $queryBuilder->leftjoin('shops', 'payment_account_shop.shop_id', 'shops.id');
        $queryBuilder->leftJoin('payment_accounts', 'payment_account_shop.payment_account_id', 'payment_accounts.id');
        $queryBuilder->leftJoin('payment_account_shop_stats', 'payment_account_shop_stats.payment_account_shop_id', 'payment_account_shop.id');
        $queryBuilder->leftJoin('currencies', 'shops.currency_id', 'currencies.id');

        return $queryBuilder
            ->defaultSort('payment_account_shop.id')
            ->select([
                'payment_account_shop.id',
                'currencies.code as shop_currency_code',
                'shops.id as shop_id',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'payment_accounts.slug as payment_account_slug',
                'payment_accounts.code as payment_account_code',
                'payment_accounts.name as payment_account_name',

                'payment_account_shop_stats.number_payments',
                'payment_account_shop_stats.amount_successfully_paid',
            ])
            ->allowedSorts(['shop_code', 'shop_name', 'number_payments', 'amount_successfully_paid', 'payment_account_code', 'payment_account_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(PaymentAccount|Shop|Fulfilment $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __('no shops'),
                        'count' => $parent->stats->number_pas,
                    ]
                );

            if ($parent instanceof PaymentAccount) {
                $table->column(key: 'shop_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                $table->column(key: 'payment_account_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            }


            $table->column(key: 'number_payments', label: __('payments'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'amount_successfully_paid', label: __('amount'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('id');
        };
    }


    public function jsonResponse(LengthAwarePaginator $paymentAccounts): AnonymousResourceCollection
    {
        return PaymentAccountShopsResource::collection($paymentAccounts);
    }


    public function htmlResponse(LengthAwarePaginator $paymentAccountShops, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof PaymentAccount) {
            $subNavigation = $this->getPaymentAccountNavigation($this->parent);
        } elseif ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigationShop($this->parent);
        } elseif ($this->parent instanceof Fulfilment) {
            $subNavigation = $this->getSubNavigation($this->parent);
        }

        return Inertia::render(
            'Org/Accounting/PaymentAccountShops',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Payment Account Shops'),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'icon'          => ['fal', 'fa-store-alt'],
                    'title'         => __('Payment Account Shops'),
                ],
                'data'        => PaymentAccountShopsResource::collection($paymentAccountShops)


            ]
        )->table($this->tableStructure($this->parent));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function asController(Organisation $organisation, PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccount);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function () use ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Payment Accounts'),
                        'icon'  => 'fal fa-bars',

                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.payment-accounts.show.shops.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('grp.org.accounting.payment-accounts.show', $routeParameters),
                $headCrumb()
            ),
            'grp.org.shops.show.dashboard.payments.accounting.accounts.index' =>
            array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            'grp.org.fulfilments.show.operations.accounting.accounts.index' =>
            array_merge(
                (new ShowFulfilment())->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
