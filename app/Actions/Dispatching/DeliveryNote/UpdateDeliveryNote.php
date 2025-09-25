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
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Models\Dispatching\DeliveryNote;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithNoStrictRules;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $deliveryNote = $this->update($deliveryNote, $modelData, ['data']);
        $changes      = Arr::except($deliveryNote->getChanges(), ['updated_at', 'last_fetched_at']);

        $deliveryNote->refresh();

        if (count($changes) > 0) {
            DeliveryNoteRecordSearch::dispatch($deliveryNote)->delay($this->hydratorsDelay);
            if (Arr::hasAny($changes, ['type', 'state', 'status'])) {
                GroupHydrateDeliveryNotes::dispatch($deliveryNote->group)->delay($this->hydratorsDelay);
                OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation)->delay($this->hydratorsDelay);
                ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop)->delay($this->hydratorsDelay);
                CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer)->delay($this->hydratorsDelay);

                OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
                    ->delay($this->hydratorsDelay);
            }


            if (Arr::hasAny($changes, ['customer_notes', 'public_notes', 'internal_notes', 'shipping_notes'])) {
                $order = $deliveryNote->orders()->first();

                if (Arr::has($changes, 'customer_notes')) {
                    $order->update(
                        [
                            'customer_notes' => $deliveryNote->customer_notes,
                        ]
                    );
                } elseif (Arr::has($changes, 'public_notes')) {
                    $order->update(
                        [
                            'public_notes' => $deliveryNote->public_notes,
                        ]
                    );
                } elseif (Arr::has($changes, 'internal_notes')) {
                    $order->update(
                        [
                            'internal_notes' => $deliveryNote->internal_notes,
                        ]
                    );
                } elseif (Arr::has($changes, 'shipping_notes')) {
                    $order->update(
                        [
                            'shipping_notes' => $deliveryNote->shipping_notes,
                        ]
                    );
                }
            }
        }

        return $deliveryNote;
    }

    public function rules(): array
    {
        $rules = [
            'reference'             => [
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
            'state'                 => ['sometimes', 'required', new Enum(DeliveryNoteStateEnum::class)],
            'email'                 => ['sometimes', 'nullable', 'string', $this->strict ? 'email' : 'string'],
            'phone'                 => ['sometimes', 'nullable', 'string'],
            'company_name'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'date'                  => ['sometimes', 'date'],
            'picker_id'             => ['sometimes'],
            'packer_id'             => ['sometimes'],
            'picker_user_id'        => ['sometimes'],
            'packer_user_id'        => ['sometimes'],
            'collection_address_id' => ['sometimes', 'nullable', Rule::exists('addresses', 'id')],
            'parcels'               => ['sometimes', 'array'],
            'customer_notes'        => ['sometimes', 'nullable', 'string', 'max:4000'],
            'public_notes'          => ['sometimes', 'nullable', 'string', 'max:4000'],
            'internal_notes'        => ['sometimes', 'nullable', 'string', 'max:4000'],
            'shipping_notes'        => ['sometimes', 'nullable', 'string', 'max:4000'],
            'dispatched_at'         => ['sometimes', 'nullable', 'date'],
            'finalised_at'          => ['sometimes', 'nullable', 'date'],
        ];

        if (!$this->strict) {
            $rules              = $this->noStrictUpdateRules($rules);
            $rules['reference'] = ['sometimes', 'string', 'max:255'];
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
