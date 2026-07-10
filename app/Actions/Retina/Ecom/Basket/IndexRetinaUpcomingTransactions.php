<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\OrgAction;
use App\Actions\RetinaAction;
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

class IndexRetinaUpcomingTransactions extends RetinaAction
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
}
