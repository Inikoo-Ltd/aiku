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
                    ->orWhereWith('delivery_notes.company_name', $value)
                    ->orWhereWith('customers.reference', $value)
                    ->orWhereWith('customers.name', $value)
                    ->orWhereWith('customers.company_name', $value)
                    ->orWhereWith('customers.contact_name', $value)
                    ->orWhereWith('addresses.address_line_1', $value)
                    ->orWhereWith('addresses.address_line_2', $value)
                    ->orWhereWith('addresses.locality', $value)
                    ->orWhereWith('addresses.postal_code', $value)
                    ->orWhereHas('orders', function ($orders) use ($value) {
                        $orders->where(function ($orders) use ($value) {
                            $orders->whereWith('orders.reference', $value)
                                ->orWhereWith('orders.customer_reference', $value)
                                ->orWhereWith('orders.external_id', $value)
                                ->orWhereWith('orders.tracking_number', $value);
                        });
                    })
                    ->orWhereHas('shipments', function ($shipments) use ($value) {
                        $shipments->where(function ($shipments) use ($value) {
                            $shipments->whereWith('shipments.tracking', $value)
                                ->orWhereWith('shipments.reference', $value);
                        });
                    });
            });
        });

        $query = QueryBuilder::for(DeliveryNote::class);
        $query->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id');
        $query->leftJoin('customers', 'delivery_notes.customer_id', '=', 'customers.id');
        $query->leftJoin('addresses', 'delivery_notes.address_id', '=', 'addresses.id');

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
            'delivery_notes.tracking_number',
            'customers.name as customer_name',
            'customers.reference as customer_reference',
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
