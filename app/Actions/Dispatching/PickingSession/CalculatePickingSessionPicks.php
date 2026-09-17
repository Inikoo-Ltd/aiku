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
        $modelData = $this->getDispatchingPercentages($pickingSession->deliveryNotesItems());

        /*
         * Every pick, not pick and pack in the session lands here, so this is where someone has
         * acted on a session flagged as ready after waiting. Releasing a waiting note sets the
         * flag after this has run, so it survives the pick that released it.
         */
        if ($pickingSession->is_waiting_ready) {
            $modelData['is_waiting_ready'] = false;
        }

        $pickingSession = $this->update($pickingSession, $modelData);

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
