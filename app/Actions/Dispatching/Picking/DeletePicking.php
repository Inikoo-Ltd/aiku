<?php
/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePicking extends OrgAction
{
    public function handle(Picking $picking): bool
    {
        return $picking->delete();
    }
    
    public function asController(Picking $picking, ActionRequest $request): bool
    {
        $this->initialisationFromShop($picking->shop, $request);

        return $this->handle($picking);
    }

    public function action(Picking $picking): bool
    {
        $this->initialisationFromShop($picking->shop, []);

        return $this->handle($picking);
    }
}
