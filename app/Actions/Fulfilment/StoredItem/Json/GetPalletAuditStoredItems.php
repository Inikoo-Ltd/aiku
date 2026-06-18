<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-16h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem\Json;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetPalletAuditStoredItems extends OrgAction
{
    public function handle(FulfilmentCustomer $parent, StoredItemAudit $storedItemAudit): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {

            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('slug', $value)
                    ->orWhereStartWith('reference', $value);
            });
        });



        $queryBuilder = QueryBuilder::for(StoredItem::class);
        $queryBuilder->where('fulfilment_customer_id', $parent->id);
        $queryBuilder->whereNotIn('stored_items.state', [
            StoredItemStateEnum::DISCONTINUING->value,
            StoredItemStateEnum::DISCONTINUED->value,
        ]);
        $queryBuilder->whereNotIn('id', $storedItemAudit->deltas()->pluck('stored_item_id'));


        $queryBuilder
            ->defaultSort('stored_items.id')
            ->select([
                'stored_items.id',
                'stored_items.slug',
                'stored_items.reference',
                'stored_items.state',
                'stored_items.name',
                'stored_items.total_quantity',
                'stored_items.number_pallets',
                'stored_items.number_audits',
            ]);


        return $queryBuilder->allowedSorts(['reference','state','name','total_quantity'])
            ->allowedFilters([$globalSearch, 'slug', 'state'])
            ->withPaginator(prefix: null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, StoredItemAudit $storedItemAudit, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $storedItemAudit);
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return StoredItemResource::collection($storedItems);
    }

}
