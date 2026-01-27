<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Jan 2026 11:37:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Http\Resources\Ordering\GetChargesInOrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetChargesInOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;

    public function asController(Order $order, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }

    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Transaction::class);
        $queryBuilder->where('transactions.order_id', $order->id)
            ->where('model_type', 'Charge')
            ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id');


        $queryBuilder
            ->defaultSort('charges.code')
            ->select([
                'transactions.id',
                'transactions.gross_amount',
                'transactions.net_amount',
                'historic_asset_id',
                'charges.code',
                'charges.type',
                'charges.name',
                'charges.description',

            ]);


        return $queryBuilder->allowedSorts(['code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $charges): AnonymousResourceCollection
    {
        return GetChargesInOrderResource::collection($charges);
    }
}
