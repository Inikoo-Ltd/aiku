<?php

namespace App\Actions\Dispatching\DeliveryNote\Json;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNoteReturnOptionResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetDeliveryNoteValidForReturn extends OrgAction
{
    public function handle(Warehouse $warehouse): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('delivery_notes.reference', $value)
                    ->orWhereWith('delivery_notes.tracking_number', $value);
            });
        });

        $query = QueryBuilder::for(DeliveryNote::class);

        $query->leftjoin('customers', 'delivery_notes.customer_id', '=', 'customers.id');
        $query->leftjoin('organisations', 'delivery_notes.organisation_id', '=', 'organisations.id');
        $query->leftjoin('shops', 'delivery_notes.shop_id', '=', 'shops.id');

        $query->where(function ($q) use ($warehouse) {
            $q->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED)
                ->where('delivery_notes.warehouse_id', $warehouse->id)
                ->where('delivery_notes.is_returned', false);
        });

        $query->where('shops.is_aiku', true);

        $selectColumns = [
            'delivery_notes.id',
            'delivery_notes.reference',
            'delivery_notes.date',
            'delivery_notes.slug',
            'delivery_notes.type',
            'customers.slug as customer_slug',
            'customers.name as customer_name',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'delivery_notes.is_returned',
            'delivery_notes.tracking_number'
        ];

        return $query
            ->defaultSort('-delivery_notes.date')
            ->select($selectColumns)
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $deliveryNotes): AnonymousResourceCollection
    {
        return DeliveryNoteReturnOptionResource::collection($deliveryNotes);
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }
}
