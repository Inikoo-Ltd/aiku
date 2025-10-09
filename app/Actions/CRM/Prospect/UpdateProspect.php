<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:45:00 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProspects;
use App\Actions\CRM\Prospect\Search\ProspectRecordSearch;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProspects;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithProcessContactNameComponents;
use App\Actions\Traits\WithProspectPrepareForValidation;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Http\Resources\Lead\ProspectResource;
use App\Models\CRM\Prospect;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateProspect extends OrgAction
{
    use WithActionUpdate;
    use WithProspectPrepareForValidation;
    use WithProcessContactNameComponents;

    private Prospect $prospect;

    public function handle(Prospect $prospect, array $modelData): Prospect
    {
        $addressData = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        if (Arr::has($modelData, 'email')) {
            $isValidEmail = true;
            if (Arr::get($modelData, 'email', '') != '' && !filter_var(Arr::get($modelData, 'email'), FILTER_VALIDATE_EMAIL)) {
                $isValidEmail = false;
            }
            data_set($modelData, 'is_valid_email', $isValidEmail);
        }

        if ($addressData) {
            UpdateAddress::run($prospect->address, $addressData);
            data_set($modelData, 'location', $prospect->address->getLocation());
        }

        if (Arr::get($modelData, 'contact_name') ) {
            data_set($modelData, 'contact_name_components', $this->processContactNameComponents(Arr::get($modelData, 'contact_name')));
        }

        $prospect = $this->update($prospect, $modelData, ['data', 'contact_name_components']);
        $changes  = Arr::except($prospect->getChanges(), ['updated_at', 'last_fetched_at']);


        if (Arr::hasAny($changes, ['name', 'contact_name', 'email', 'company_name', 'state'])) {
            ProspectRecordSearch::dispatch($prospect);
        }


        if (Arr::hasAny($changes, [
            'state',
            'contacted_state',
            'fail_status',
            'success_status',
        ])) {
            OrganisationHydrateProspects::dispatch($prospect->organisation)->delay($this->hydratorsDelay);
            ShopHydrateProspects::dispatch($prospect->shop)->delay($this->hydratorsDelay);
        }


        return $prospect;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'state'                  => ['sometimes', Rule::enum(ProspectStateEnum::class)],
            'contacted_state'        => ['sometimes', Rule::enum(ProspectContactedStateEnum::class)],
            'fail_status'            => ['sometimes', 'nullable', Rule::enum(ProspectFailStatusEnum::class)],
            'success_status'         => ['sometimes', 'nullable', Rule::enum(ProspectSuccessStatusEnum::class)],
            'dont_contact_me'        => ['sometimes', 'boolean'],
            'can_contact_by_email'   => ['sometimes', 'boolean'],
            'can_contact_by_phone'   => ['sometimes', 'boolean'],
            'can_contact_by_address' => ['sometimes', 'boolean'],

            'last_contacted_at' => 'sometimes|nullable|date',
            'contact_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'address'           => ['sometimes', 'nullable', new ValidAddress()],
            'email'             => [
                'sometimes',
                $this->strict ? 'email' : 'string:500',
                new IUnique(
                    table: 'prospects',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->prospect->id]

                    ]
                ),

            ],
            'phone'             => [
                'sometimes',
                'nullable',
                $this->strict ? new Phone() : 'string:255',
                new IUnique(
                    table: 'prospects',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->prospect->id]

                    ]
                ),
            ],
            'contact_website'   => [
                'sometimes',
                'nullable',
                $this->strict ? 'url:http,https' : 'string:255',
                new IUnique(
                    table: 'prospects',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->prospect->id]

                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules['phone']           = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['contact_website'] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['customer_id']     = ['sometimes', 'nullable', 'integer'];
        }


        return $rules;
    }

    public function asController(Prospect $prospect, ActionRequest $request): Prospect
    {
        $this->prospect = $prospect;
        $this->initialisationFromShop($prospect->shop, $request);

        return $this->handle($prospect, $this->validatedData);
    }

    public function action(Prospect $prospect, $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Prospect
    {
        $this->strict = $strict;
        if (!$audit) {
            Prospect::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->prospect       = $prospect;
        $this->initialisationFromShop($prospect->shop, $modelData);

        return $this->handle($prospect, $this->validatedData);
    }

    public function jsonResponse(Prospect $prospect): ProspectResource
    {
        return new ProspectResource($prospect);
    }
}
