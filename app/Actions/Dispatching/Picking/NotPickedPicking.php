<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Models\Dispatching\Picking;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class NotPickedPicking extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;


    public function handle(Picking $picking, array $modelData): Picking
    {
        return $this->update($picking, $modelData);

    }

    public function rules(): array
    {
        return [
            'not_picked_reason'  => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'    => ['sometimes', 'string'],
        ];
    }

    public function asController(Picking $picking, ActionRequest $request): Picking
    {
        $this->initialisationFromShop($picking->shop, $request);

        return $this->handle($picking, $this->validatedData);
    }

    public function action(Picking $picking, array $modelData): Picking
    {
        $this->initialisationFromShop($picking->shop, $modelData);

        return $this->handle($picking, $this->validatedData);
    }
}
