<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\OrgAction;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Http\Resources\Ordering\UpcomingTransactionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\UpcomingTransaction;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexUpcomingTransactions extends OrgAction
{
    private Customer $parent;

    public function handle(Customer $customer, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('products.code', $value)
                    ->orWhereStartWith('products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(UpcomingTransaction::class);
        $query
            ->where('upcoming_transactions.customer_id', $customer->id)
            ->where('upcoming_transactions.state', UpcomingTransactionStateEnum::READY);

        $query->leftJoin('products', 'upcoming_transactions.product_id', '=', 'products.id');

        return $query->defaultSort('upcoming_transactions.id')
            ->select([
                'upcoming_transactions.id',
                'upcoming_transactions.customer_id',
                'upcoming_transactions.product_id',
                'upcoming_transactions.order_id',
                'upcoming_transactions.transaction_id',
                'upcoming_transactions.quantity',
                'upcoming_transactions.public_notes',
                'upcoming_transactions.private_notes',
                'upcoming_transactions.type',
                'upcoming_transactions.state',
                'upcoming_transactions.created_at',
                'upcoming_transactions.updated_at',
                'products.code as product_code',
                'products.name as product_name',
            ])
            ->allowedSorts(['id', 'product_code', 'product_name', 'quantity', 'type', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("No upcoming transactions found"),
                    ]
                );

            $table->column(key: 'product_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'product_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'notes', label: __('Notes'), canBeHidden: false, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $upcomingTransactions): AnonymousResourceCollection
    {
        return UpcomingTransactionsResource::collection($upcomingTransactions);
    }

    public function htmlResponse(LengthAwarePaginator $upcomingTransactions, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Ordering/UpcomingTransactions',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('Upcoming Transactions'),
                'pageHead'    => [
                    'title' => __('Upcoming Transactions'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clock'],
                        'title' => __('Upcoming Transactions'),
                    ],
                ],
                'data'        => UpcomingTransactionsResource::collection($upcomingTransactions),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($customer->shop, $request);

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
                        'label' => __('Upcoming Transactions'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.show.upcoming_transactions.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'grp.org.shops.show.crm.customers.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ]
                )
            ),
            default => []
        };
    }
}
