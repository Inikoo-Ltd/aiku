<?php

/*
 * Author: Kirin
 * Created: Wed, 14 May 2025 15:44 Malaysia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\PickingSession\CalculatePickingSessionPicks;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithDispatchingPercentages;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;

class CalculateDeliveryNotePercentage extends OrgAction
{
    use WithActionUpdate;
    use WithDispatchingPercentages;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = $this->update($deliveryNote, $this->getDispatchingPercentages($deliveryNote->deliveryNoteItems()));

        if ($deliveryNote->pickingSessions) {
            foreach ($deliveryNote->pickingSessions as $pickingSession) {
                CalculatePickingSessionPicks::run($pickingSession);
            }
        }

        return $deliveryNote;
    }

    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }

    public function getCommandSignature(): string
    {
        return 'delivery-note:calculate-percentage {deliveryNote}';
    }

    public function asCommand(Command $command): int
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('deliveryNote'))->firstOrFail();
        $this->handle($deliveryNote);
        return 0;

    }
}
