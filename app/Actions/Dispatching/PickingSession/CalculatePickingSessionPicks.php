<?php

/*
 * Author: Arya Permana - Kirin
 * Created: Thu, 22 May 2025 15:44 Malaysia Time
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithDispatchingPercentages;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class CalculatePickingSessionPicks extends OrgAction
{
    use WithActionUpdate;
    use WithDispatchingPercentages;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $pickingSession = $this->update($pickingSession, $this->getDispatchingPercentages($pickingSession->deliveryNotesItems()));

        AutoFinishPickingPickingSession::run($pickingSession);

        return $pickingSession;
    }

    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }

    public function getCommandSignature(): string
    {
        return 'picking_session:calculate {picking_session}';
    }

    public function getCommandDescription(): string
    {
        return 'Calculate picking session picks';
    }

    public function asCommand(Command $command): int
    {
        $pickingSession = PickingSession::where('slug', $command->argument('picking_session'))->firstOrFail();

        $this->handle($pickingSession);

        return 0;
    }


}
