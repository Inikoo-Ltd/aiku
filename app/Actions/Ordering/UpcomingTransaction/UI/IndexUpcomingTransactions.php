<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction\UI;

use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\Ordering\UpcomingTransaction;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexUpcomingTransactions extends OrgAction
{
    public function handle(Customer $customer, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('products.code', $value)
                    ->orWhereStartWith('products.name', $value);
            });
        });

        $query = QueryBuilder::for(UpcomingTransaction::class);
        $query->where('upcoming_transactions.customer_id', $customer->id);

        $query->leftJoin('products', 'upcoming_transactions.product_id', '=', 'products.id');

        return $query->defaultSort('upcoming_transactions.id')
            ->select([
                'upcoming_transactions.id',
                'upcoming_transactions.customer_id',
                'upcoming_transactions.product_id',
                'upcoming_transactions.order_id',
                'upcoming_transactions.transaction_id',
                'upcoming_transactions.quantity',
                'upcoming_transactions.notes',
                'upcoming_transactions.type',
                'upcoming_transactions.state',
                'upcoming_transactions.created_at',
                'upcoming_transactions.updated_at',
                'products.code as product_code',
                'products.name as product_name',
            ])
            ->allowedSorts(['id', 'product_code', 'product_name', 'quantity', 'type', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: 'upcoming_transactions')
            ->withQueryString();
    }

    public function action(Customer $customer, ?string $prefix = null): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisationFromShop($customer->shop, []);

        return $this->handle($customer, $prefix);
    }

    public function asController(Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer);
    }
}
