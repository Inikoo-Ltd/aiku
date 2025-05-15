<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $state
 * @property mixed $type
 * @property mixed $customer_reference
 * @property mixed $number_pallets
 * @property mixed $number_services
 * @property mixed $number_stored_items
 * @property mixed $number_physical_goods
 * @property mixed $total_amount
 * @property mixed $currency_code
 * @property mixed $date
 */
class PalletReturnsResource extends JsonResource
{
    public function toArray($request): array
    {


        if ($this->state == PalletReturnStateEnum::PICKING) {
            $query = PalletReturnItem::where('pallet_return_id', $this->id);
            $totalStoredItem = (int) PalletStoredItem::whereIn('pallet_id', $query->pluck('pallet_id'))->sum('quantity');


            $totalStoredItemPicking = (int) $query->sum('quantity_ordered');
            $totalStoredItem  += $totalStoredItemPicking;

            $percentageStoredItem = $totalStoredItem > 0 ? round(($totalStoredItemPicking / $totalStoredItem) * 100, 2) : 0;
            $result = '' . $totalStoredItemPicking . ' / ' . $totalStoredItem . ' (' . $percentageStoredItem . '%)';

            if ($this->type == PalletReturnTypeEnum::PALLET) {
                $this->number_pallets = $result;
            } else {
                $this->number_stored_items = $result;
            }
        }

        return [
            'id'                    => $this->id,
            'created_at'            => $this->created_at,
            'slug'                  => $this->slug,
            'reference'             => $this->reference,
            'platform_name'         => $this->platform_name,
            'state'                 => $this->state,
            'state_label'           => $this->state->labels()[$this->state->value],
            'state_icon'            => $this->state->stateIcon()[$this->state->value],
            'type'                  => $this->type,
            'type_label'            => $this->type->labels()[$this->type->value],
            'type_icon'             => $this->type->stateIcon()[$this->type->value],
            'customer_reference'    => $this->customer_reference,
            'number_pallets'        => $this->number_pallets,
            'number_stored_items'     => $this->number_stored_items,
            'number_services'       => $this->number_services,
            'number_physical_goods' => $this->number_physical_goods,
            'date'                  => $this->date,
            'total_amount'          => $this->total_amount,
            'currency_code'         => $this->currency_code,
            'confirmed_at'          => $this->confirmed_at,
            'picked_at'             => $this->picked_at,
            'picking_at'            => $this->picking_at,
            'dispatched_at'         => $this->dispatched_at,
            'cancel_at'             => $this->cancel_at,
        ];
    }
}
