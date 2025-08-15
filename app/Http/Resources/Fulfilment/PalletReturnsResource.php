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
 * @property mixed $created_at
 * @property mixed $platform_name
 * @property mixed $confirmed_at
 * @property mixed $picked_at
 * @property mixed $picking_at
 * @property mixed $dispatched_at
 * @property mixed $cancel_at
 * @property mixed $unique_stored_item_count
 * @property mixed $customer_has_platform_id
 */
class PalletReturnsResource extends JsonResource
{
    public function toArray($request): array
    {

        $numberPallets     = $this->number_pallets;
        $numberStoredItems = $this->number_stored_items;

        $uniqueStoredItemsCount = $this->unique_stored_item_count;

        if ($this->type == PalletReturnTypeEnum::PALLET) {
            $numberStoredItems = 'NA';
            $uniqueStoredItemsCount = 'NA';
        }

        if ($this->state == PalletReturnStateEnum::PICKING) {
            $query = PalletReturnItem::where('pallet_return_id', $this->id);

            $totalStoredItemPicking = (int)$query->sum('quantity_ordered');
            $totalStoredItemPicked  = (int)$query->where('state', PalletReturnStateEnum::PICKED)->sum('quantity_ordered');


            $percentageStoredItem = $totalStoredItemPicking > 0 ? round(($totalStoredItemPicked / $totalStoredItemPicking) * 100, 2) : 0;
            $result               = ''.$totalStoredItemPicked.' / '.$totalStoredItemPicking.' ('.$percentageStoredItem.'%)';

            if ($this->type == PalletReturnTypeEnum::PALLET) {
                $numberPallets     = $result;
                $numberStoredItems = 'NA';
            } else {
                $numberStoredItems = $result;
            }
        }

        return [
            'id'                       => $this->id,
            'created_at'               => $this->created_at,
            'slug'                     => $this->slug,
            'reference'                => $this->reference,
            'platform_name'            => $this->platform_name,
            'state'                    => $this->state,
            'state_label'              => $this->state->labels()[$this->state->value],
            'state_icon'               => $this->state->stateIcon()[$this->state->value],
            'type'                     => $this->type,
            'type_label'               => $this->type->labels()[$this->type->value],
            'type_icon'                => $this->type->stateIcon()[$this->type->value],
            'customer_reference'       => $this->customer_reference,
            'number_pallets'           => $numberPallets,
            'number_stored_items'      => $numberStoredItems,
            'number_services'          => $this->number_services,
            'number_physical_goods'    => $this->number_physical_goods,
            'date'                     => $this->date,
            'total_amount'             => $this->total_amount,
            'currency_code'            => $this->currency_code,
            'confirmed_at'             => $this->confirmed_at,
            'picked_at'                => $this->picked_at,
            'picking_at'               => $this->picking_at,
            'dispatched_at'            => $this->dispatched_at,
            'cancel_at'                => $this->cancel_at,
            'unique_stored_item_count' => $uniqueStoredItemsCount,
            'customer_has_platform_id' => $this->customer_has_platform_id ?? null,
        ];
    }
}
