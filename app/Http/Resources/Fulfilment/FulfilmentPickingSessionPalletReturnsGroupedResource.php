<?php

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfilmentPickingSessionPalletReturnsGroupedResource extends JsonResource
{
    public function toArray($request): array
    {
        $palletReturn = $this->resource instanceof PalletReturn ? $this->resource : PalletReturn::find($this->id);
        $pickingSessionId = $this->picking_session_id ?? null;

        $pallets = [];
        if ($palletReturn && $pickingSessionId) {
            $query = Pallet::query()
                ->join('pallet_return_items', 'pallet_return_items.pallet_id', '=', 'pallets.id')
                ->join('pallet_returns', 'pallet_returns.id', '=', 'pallet_return_items.pallet_return_id')
                ->leftJoin('fulfilments', 'pallets.fulfilment_id', '=', 'fulfilments.id')
                ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', '=', 'pallets.fulfilment_customer_id')
                ->leftJoin('customers', 'customers.id', '=', 'fulfilment_customers.customer_id')
                ->leftJoin('locations', 'locations.id', '=', 'pallets.location_id')
                ->where('pallet_return_items.picking_session_id', $pickingSessionId)
                ->where('pallet_return_items.pallet_return_id', $palletReturn->id)
                ->where('pallet_return_items.type', 'Pallet')
                ->orderBy('pallets.id')
                ->select(
                    'pallet_return_items.id',
                    'pallet_return_items.pallet_return_id as pallet_return_id',
                    'pallet_returns.reference as pallet_return_reference',
                    'pallet_returns.slug as pallet_return_slug',
                    'pallet_returns.type as pallet_return_type',
                    'pallets.id as pallet_id',
                    'pallets.slug',
                    'pallets.reference',
                    'pallets.customer_reference',
                    'pallets.notes',
                    'pallet_return_items.state as pivot_state',
                    'pallets.state',
                    'pallets.status',
                    'pallets.rental_id',
                    'pallets.type',
                    'pallets.received_at',
                    'pallets.location_id',
                    'pallets.fulfilment_customer_id',
                    'pallets.warehouse_id',
                    'pallets.pallet_delivery_id',
                    'pallets.pallet_return_id',
                    'locations.slug as location_slug',
                    'locations.code as location_code',
                    DB::raw('fulfilments.slug as fulfilment_slug'),
                    DB::raw('customers.slug as fulfilment_customer_slug'),
                    DB::raw('pallet_returns.state as pallet_return_state')
                )
                ->get();

            $pallets = PalletReturnItemsUIResource::collection($query)->resolve();
        }

        return [
            'pallet_return_id'        => $palletReturn->id,
            'pallet_return_slug'      => $palletReturn->slug,
            'pallet_return_reference' => $palletReturn->reference,
            'pallet_return_state'     => $palletReturn->state?->value,
            'state_icon'              => $palletReturn->state
                ? $palletReturn->state->stateIcon()[$palletReturn->state->value]
                : null,
            'pallet_return_type'      => $palletReturn->type?->value,
            'pallets'                 => $pallets,
        ];
    }
}
