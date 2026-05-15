<?php

namespace App\Actions\Dispatching\DeliveryNote\Json;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNote\DeliveryNotesForSelectResource;
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
                    ->orWhereWith('delivery_notes.tracking_number', $value)
                    ->orWhereWith('delivery_notes.contact_name', $value)
                    ->orWhereWith('delivery_notes.company_name', $value);
            });
        });

        $query = QueryBuilder::for(DeliveryNote::class);
        $query->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id');

        $query->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED);
        $query->where('delivery_notes.organisation_id', $warehouse->organisation_id);
        $query->where('delivery_notes.is_returned', false);
        
        $query->where('shops.is_aiku', true);


        $selectColumns = [
            'delivery_notes.id',
            'delivery_notes.reference',
            'delivery_notes.date',
            'delivery_notes.slug',
            'delivery_notes.contact_name',
            'delivery_notes.company_name',
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
        return DeliveryNotesForSelectResource::collection($deliveryNotes);
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }
}
