<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-15h-21m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\Accounting\PaymentAccount\WithPaymentAccountSubNavigation;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\UI\CRM\CustomersTabsEnum;
use App\Http\Resources\Accounting\CustomersInPaymentAccountResource;
use App\Http\Resources\CRM\CustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentAccount;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
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

class IndexCustomersInPaymentAccount extends OrgAction
{
    use WithAccountingAuthorisation;
    use WithPaymentAccountSubNavigation;

    private PaymentAccount $paymentAccount;

    public function asController(Organisation $organisation, PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->paymentAccount = $paymentAccount;
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccount);
    }

    public function handle(PaymentAccount $paymentAccount, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhere('customers.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class)
            ->select('customers.*')
            ->selectSub(function ($query) use ($paymentAccount) {
                $query->from('payments')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('payments.customer_id', 'customers.id')
                    ->where('payments.status', PaymentStatusEnum::SUCCESS)
                    ->where('payments.payment_account_id', $paymentAccount->id);
            }, 'total_payments')
            ->selectSub(function ($query) use ($paymentAccount) {
                $query->from('payments')
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('payments.customer_id', 'customers.id')
                    ->where('payments.status', PaymentStatusEnum::SUCCESS)
                    ->where('payments.payment_account_id', $paymentAccount->id);
            }, 'total_amount_paid')
            ->leftJoin('payments', 'customers.id', '=', 'payments.customer_id')
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->where('payments.payment_account_id', $paymentAccount->id)
            ->leftJoin('shops', 'customers.shop_id', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id')
            ->groupBy('customers.id',
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.slug',
                'customers.created_at',
                'currencies.code',
                'shops.code',
                'shops.name'
            );

        return $queryBuilder
            ->defaultSort('-created_at')
            ->addSelect([
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.slug',
                'customers.created_at',
                'currencies.code as currency_code',
                'shops.code as shop_code',
                'shops.name as shop_name',
            ])
            ->allowedSorts([])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(PaymentAccount $paymentAccount, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($paymentAccount, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState([
                            'title'       => __("No customers found"),
                            'description' => __('No customers found for this payment account.'),
                            'count'       => $paymentAccount->stats->number_customers,
                ]
                )
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'shop_code', label: __('shop'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_payments', label: __('payments'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_amount_paid', label: __('amount'), canBeHidden: false, sortable: true, searchable: true);
            $table->defaultSort('-created_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $subNavigation = $this->getPaymentAccountNavigation(
            $this->paymentAccount,
        );
        return Inertia::render(
            'Org/Accounting/PaymentAccountCustomers',
            [
                'breadcrumbs'                       => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                             => __('customers'),
                'pageHead'                          => [
                    'subNavigation' => $subNavigation,
                    'title'         => __('customers'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('customer')
                    ],
                ],
                'data'              => CustomersInPaymentAccountResource::collection($customers),
            ]
        )->table($this->tableStructure(paymentAccount: $this->paymentAccount));
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
            'grp.org.accounting.payment-accounts.show.customers.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('grp.org.accounting.payment-accounts.show', $routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
