<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:45:00 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProspects;
use App\Actions\CRM\Prospect\Search\ProspectRecordSearch;
use App\Actions\Helpers\Query\HydrateModelTypeQueries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProspects;
use App\Actions\Traits\WithCheckCanContactByEmail;
use App\Actions\Traits\WithCheckCanContactByPhone;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithProspectPrepareForValidation;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProspect extends OrgAction
{
    use WithAttributes;
    use WithProspectPrepareForValidation;
    use WithCheckCanContactByEmail;
    use WithCheckCanContactByPhone;
    use WithModelAddressActions;


    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Prospect
    {
        $addressData = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);


        $isValidEmail = true;
        if (Arr::get($modelData, 'email', '') != '' && !filter_var(Arr::get($modelData, 'email'), FILTER_VALIDATE_EMAIL)) {
            $isValidEmail = false;
        }
        data_set($modelData, 'is_valid_email', $isValidEmail);


        if (
            !$isValidEmail
            && !Arr::has($modelData, 'phone')
            && (
                !Arr::has($modelData, 'state')
                || Arr::get($modelData, 'state') == ProspectStateEnum::NO_CONTACTED
            )
        ) {
            data_set($modelData, 'state', ProspectStateEnum::FAIL);
            data_set($modelData, 'fail_status', ProspectFailStatusEnum::INVALID);
        }

        $prospect = DB::transaction(function () use ($shop, $modelData, $addressData) {
            /** @var Prospect $prospect */
            $prospect = $shop->prospects()->create($modelData);

            $prospect->updateQuietly(
                [
                    'can_contact_by_email' => $this->canContactByEmail($prospect),
                    'can_contact_by_phone' => $this->canContactByPhone($prospect)
                ]
            );

            if ($addressData) {
                $prospect = $this->addAddressToModelFromArray(
                    model: $prospect,
                    addressData: $addressData,
                    scope: 'contact'
                );
            }

            return $prospect;
        });

        ProspectRecordSearch::dispatch($prospect)->delay($this->hydratorsDelay);
        OrganisationHydrateProspects::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        ShopHydrateProspects::dispatch($shop)->delay($this->hydratorsDelay);

        HydrateModelTypeQueries::dispatch('Prospect')->delay($this->hydratorsDelay);


        $prospect->refresh();

        return $prospect;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function afterValidator(Validator $validator)
    {
        if (!trim($this->get('contact_name'))) {
            $firstName = trim($this->get('first_name'));
            $lastName  = trim($this->get('last_name'));

            if ($firstName || $lastName) {
                $this->set('contact_name', trim($firstName . ' ' . $lastName));
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'contacted_state'   => ['sometimes', Rule::enum(ProspectContactedStateEnum::class)],
            'fail_status'       => ['sometimes', 'nullable', Rule::enum(ProspectFailStatusEnum::class)],
            'success_status'    => ['sometimes', 'nullable', Rule::enum(ProspectSuccessStatusEnum::class)],
            'dont_contact_me'   => ['sometimes', 'boolean'],
            'state'             => ['sometimes', new Enum(ProspectStateEnum::class)],
            'data'              => 'sometimes|array',
            'last_contacted_at' => 'sometimes|nullable|date',
            'address'           => ['sometimes', 'nullable', new ValidAddress()],
            'contact_name'      => ['nullable', 'string', 'max:255'],
            'first_name'       => ['nullable', 'string', 'max:255'],
            'last_name'        => ['nullable', 'string', 'max:255'],
            'company_name'      => ['nullable', 'string', 'max:255'],
            'email'             => [
                $this->strict ? 'email' : 'string:500',
                new IUnique(
                    table: 'prospects',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],

                    ]
                ),

            ],
            'phone'             => [
                'required_without:email',
                'nullable',
                new Phone(),
                new IUnique(
                    table: 'prospects',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],

            'contact_website' => [
                'nullable',
                $this->strict ? 'url:http,https' : 'string:500',

            ],

        ];

        if (!$this->strict) {
            $rules['phone']      = [
                'nullable',
                'string',
                'min:5',
                'max:24'
            ];
            $rules['created_at'] = ['sometimes', 'date'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['deleted_at'] = ['sometimes', 'nullable', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Prospect
    {
        if (!$audit) {
            Prospect::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

}
