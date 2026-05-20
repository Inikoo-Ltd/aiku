<?php

namespace App\Actions\Accounting\CreditTransaction\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Http\Resources\Accounting\CustomerCreditResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerCredit extends OrgAction
{
    use AsAction;

    private int $records = 0;
    private ?string $beforeDate = null;

    public function handle(Organisation $organisation, string $beforeDate, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('customers.reference', $value)
                    ->orWhereWith('customers.name', $value)
                    ->orWhereWith('customers.email', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class);

        $queryBuilder
            ->where('customers.organisation_id', $organisation->id)
            ->join('shops', 'customers.shop_id', '=', 'shops.id')
            ->leftJoin('credit_transactions', function ($join) use ($beforeDate) {
                $join->on('credit_transactions.customer_id', '=', 'customers.id')
                    ->where('credit_transactions.date', '<', $beforeDate);
            })
            ->leftJoin('currencies', 'credit_transactions.currency_id', '=', 'currencies.id')
            ->groupBy('customers.id', 'customers.slug', 'customers.reference', 'customers.name', 'customers.email', 'shops.code')
            ->havingRaw('COUNT(credit_transactions.id) > 0')
            ->selectRaw('
                customers.id,
                customers.slug,
                customers.reference,
                customers.name,
                customers.email,
                shops.code as shop_code,
                COALESCE(SUM(credit_transactions.amount), 0) as credit_balance,
                MAX(credit_transactions.date) as latest_transaction_date,
                (SELECT c2.symbol FROM credit_transactions ct2
                    LEFT JOIN currencies c2 ON ct2.currency_id = c2.id
                    WHERE ct2.customer_id = customers.id AND ct2.date < ?
                    ORDER BY ct2.date DESC LIMIT 1) as currency_symbol,
                (SELECT c2.code FROM credit_transactions ct2
                    LEFT JOIN currencies c2 ON ct2.currency_id = c2.id
                    WHERE ct2.customer_id = customers.id AND ct2.date < ?
                    ORDER BY ct2.date DESC LIMIT 1) as currency_code
            ', [$beforeDate, $beforeDate]);

        $this->records = $queryBuilder->toBase()->getCountForPagination();

        return $queryBuilder
            ->defaultSort('-latest_transaction_date')
            ->allowedSorts(['reference', 'name', 'email', 'shop_code', 'credit_balance', 'latest_transaction_date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No customers with credits'),
                    'description' => __('No customers found with credit transactions before the selected date.'),
                    'count'       => $this->records,
                ])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('Email'), sortable: true, searchable: true)
                ->column(key: 'shop_code', label: __('Shop Code'), sortable: true)
                ->column(key: 'latest_transaction_date', label: __('Latest Transaction'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'credit_balance', label: __('Balance'), canBeHidden: false, sortable: true, type: 'currency')
                ->defaultSort('-latest_transaction_date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): ?LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        $this->beforeDate = $request->input('before_date');

        if (!$this->beforeDate) {
            return null;
        }

        return $this->handle($organisation, $this->beforeDate);
    }

    public function htmlResponse(?LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Reports/CustomerCreditReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Customer Credit Report'),
                'pageHead'    => [
                    'title' => __('Customer Credit Report'),
                    'icon'  => [
                        'title' => __('Customer Credit'),
                        'icon'  => 'fal fa-credit-card',
                    ],
                ],
                'data'        => $customers ? CustomerCreditResource::collection($customers) : null,
                'before_date' => $this->beforeDate,
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomerCreditResource::collection($customers);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-credit-card',
                        'label' => __('Customer Credit'),
                        'route' => [
                            'name'       => 'grp.org.reports.customer-credit',
                            'parameters' => $routeParameters,
                        ],
                    ],
                ],
            ]
        );
    }
}
