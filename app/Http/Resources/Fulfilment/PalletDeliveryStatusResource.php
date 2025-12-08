<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletDeliveryStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PalletDelivery $palletDelivery */
        $palletDelivery = $this;
        $numberPalletsStateBookingIn = $palletDelivery->pallets()->where('state', PalletStateEnum::BOOKING_IN)->count();
        $numberPalletsRentalNotSet = $palletDelivery->pallets()->whereNull('rental_id')->count();

        return [
            'id' => $palletDelivery->id,
            'slug' => $palletDelivery->slug,
            'state' => $palletDelivery->state->value,
            'number_pallets_received' => $numberPalletsStateBookingIn,
            'number_pallets_rental_not_set' => $numberPalletsRentalNotSet,
            'can_finish_booking_in' => $palletDelivery->state == PalletDeliveryStateEnum::BOOKING_IN && $numberPalletsStateBookingIn == 0 and $numberPalletsRentalNotSet == 0,

        ];
    }
}
