<?php

/*
 * author Louis Perez
 * created on 18-03-2026-12h-27m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetTempPickerToDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithNoStrictRules;
    use HasDeliveryNoteHydrators;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        session()->put('temp_handling_delivery_note', [
            'value' => $deliveryNote->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        return $deliveryNote;
    }

    public function rules(): array
    {
        return [
            'picker_user_id'    => ['sometimes', Rule::exists('users', 'id')]
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, DeliveryNote $deliveryNote, ActionRequest $request, int $hydratorsDelay = 0): DeliveryNote
    {
        $this->deliveryNote   = $deliveryNote;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
