<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Validation\Rule;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetPalletAsDamaged extends OrgAction
{
    use WithPalletIncidentAction;

    public function handle(Pallet $pallet, $modelData): Pallet
    {
        $oldLocation = $pallet->location;

        $pallet = $this->processIncident($pallet, PalletStateEnum::DAMAGED, $modelData);

        if ($oldLocation) {
            LocationHydratePallets::dispatch($oldLocation);
        }

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction) {
            $this->set('reporter_id', $request->user()->id);
        }
    }

    public function rules(): array
    {
        return [
            'message'     => ['nullable', 'string'],
            'reporter_id' => ['sometimes', 'required', Rule::exists('users', 'id')->where('group_id', $this->organisation->group_id)],
        ];
    }


    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisationFromWarehouse($pallet->warehouse, $request);

        if ($pallet->status !== PalletStatusEnum::STORING) {
            throw ValidationException::withMessages(['pallet' => __('Only a stored pallet can be set as damaged.')]);
        }

        return $this->handle($pallet, $this->validatedData);
    }

    public function action(Pallet $pallet, array $modelData): Pallet
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($pallet->warehouse, $modelData);

        return $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
