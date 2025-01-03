<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\Dispatching\DeliveryNote\Search\DeliveryNoteRecordSearch;
use App\Actions\Dispatching\Picking\AssignPackerToPicking;
use App\Actions\Dispatching\Picking\AssignPickerToPicking;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Models\Dispatching\DeliveryNote;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;


    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $deliveryNote = $this->update($deliveryNote, $modelData, ['data']);
        $changes      = $deliveryNote->getChanges();

        $deliveryNote->refresh();

        if (Arr::get($modelData, 'picker_id')) {
            foreach ($deliveryNote->deliveryNoteItems as $item) {
                AssignPickerToPicking::make()->action(
                    $item->pickings,
                    [
                        'picker_id' => $deliveryNote->picker_id
                    ]
                );
            }
        }

        if (Arr::get($modelData, 'packer_id')) {
            foreach ($deliveryNote->deliveryNoteItems as $item) {
                AssignPackerToPicking::make()->action(
                    $item->pickings,
                    [
                        'packer_id' => $deliveryNote->packer_id
                    ]
                );
            }
        }

        DeliveryNoteRecordSearch::dispatch($deliveryNote);

        if (Arr::hasAny($changes, ['type', 'state', 'status'])) {
            DeliveryNoteRecordSearch::dispatch($deliveryNote)->delay($this->hydratorsDelay);
            GroupHydrateDeliveryNotes::dispatch($deliveryNote->group)->delay($this->hydratorsDelay);
            OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation)->delay($this->hydratorsDelay);
            ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop)->delay($this->hydratorsDelay);
            CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer)->delay($this->hydratorsDelay);
        }


        return $deliveryNote;
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'string',
                'max:64',
                new IUnique(
                    table: 'delivery_notes',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                        ['column' => 'id', 'value' => $this->deliveryNote->id, 'operator' => '!=']
                    ]
                ),
            ],
            'state'     => ['sometimes', 'required', new Enum(DeliveryNoteStateEnum::class)],
            'email'     => ['sometimes', 'nullable', 'string', $this->strict ? 'email' : 'string'],
            'phone'     => ['sometimes', 'nullable', 'string'],
            'date'      => ['sometimes', 'date'],
            'picker_id' => ['sometimes'],
            'packer_id' => ['sometimes']
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(DeliveryNote $deliveryNote, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): DeliveryNote
    {
        $this->strict = $strict;
        if (!$audit) {
            DeliveryNote::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->deliveryNote   = $deliveryNote;

        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function jsonResponse(DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        return new DeliveryNoteResource($deliveryNote);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request, int $hydratorsDelay = 0): DeliveryNote
    {
        $this->deliveryNote   = $deliveryNote;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
