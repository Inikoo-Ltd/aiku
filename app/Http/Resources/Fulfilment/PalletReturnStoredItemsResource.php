<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Feb 2024 11:07:20 Malaysia Time, Bali Airport, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $customer_reference
 * @property mixed $fulfilment_customer_id
 * @property mixed $slug
 * @property mixed $notes
 * @property mixed $state
 * @property mixed $type
 * @property mixed $pivot
 * @property mixed $status
 * @property mixed $location_slug
 * @property mixed $location_code
 * @property mixed $location_id
 * @property mixed $warehouse_id
 * @property mixed $pallet_delivery_id
 * @property mixed $pallet_return_id
 * @property mixed $pallet_id
 * @property mixed $fulfilment_customer_name
 * @property mixed $fulfilment_customer_slug
 */
class PalletReturnStoredItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $palletReturn = PalletReturn::where('slug', $request->route()->originalParameters()['palletReturn'])->first();

        return [
            'id'                                   => $this->id,
            'slug'                                 => $this->slug,
            'reference'                            => $this->reference,
            'state'                                => $this->state,
            'state_icon'                           => $this->state->stateIcon()[$this->state->value],
            'total_quantity'                       => intval($this->pallets->sum('pivot.quantity')),
            'quantity'                             => intval($this->pallets->sum('pivot.quantity')),
            'damaged_quantity'                     => intval($this->pallets->sum('pivot.damaged_quantity')),
            'is_checked'                           => $this->whereHas('palletReturns', function ($query) use ($palletReturn) {
                $query->where('pallet_return_id', $palletReturn->id);
            })->count() > 0
        ];
    }
}
