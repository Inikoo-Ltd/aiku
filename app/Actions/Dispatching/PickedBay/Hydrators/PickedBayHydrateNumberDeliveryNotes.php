<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Jun 2025 15:37:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickedBay\Hydrators;

use App\Models\Inventory\PickedBay;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PickedBayHydrateNumberDeliveryNotes implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $pickedBayId): string
    {
        return $pickedBayId ?? 'empty';
    }

    public function handle(int|null $pickedBayId): void
    {
        if (!$pickedBayId) {
            return;
        }
        $pickedBay = PickedBay::find($pickedBayId);

        $stats = [
            'number_delivery_notes' => $pickedBay->deliveryNotes->count(),
        ];


        $pickedBay->update($stats);
    }


}
