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

class UpdatePickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession, array $modelData): PickingSession
    {
        return $this->update($pickingSession, $modelData);

    }

    public function rules(): array
    {
        return [
            'state'  => ['sometimes', Rule::enum(PickingSessionStateEnum::class)],
            'user_id' => ['sometimes', 'exists:users,id'],
        ];

    }

    public function action(PickingSession $pickingSession, array $modelData): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $modelData);
        return $this->handle($pickingSession, $this->validatedData);

    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData);
    }
}
