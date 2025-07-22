<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-12h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StartPickPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession, array $modelData): PickingSession
    {
        data_set($modelData, 'state', PickingSessionStateEnum::ACTIVE);
        data_set($modelData, 'start_at', now());

        $pickingSession = $this->update($pickingSession, $modelData);

        return $pickingSession;
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request)
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData);
    }
}
