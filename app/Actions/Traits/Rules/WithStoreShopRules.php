<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 11:32:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Rules;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Validation\Rule;

trait WithStoreShopRules
{
    protected function getStoreShopRules(): array
    {
        $rules = [
            'registration_needs_approval' => ['sometimes', 'boolean'],
            'master_shop_id' => [
                'sometimes',
                'nullable',
                Rule::Exists('master_shops', 'id')->where('group_id', $this->organisation->group_id)

            ],

            'code'                     => [
                'required',
                'max:8',
                'alpha_dash',
                new IUnique(
                    table: 'shops',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                    ]
                ),


            ],
            'name'                     => ['required', 'string', 'max:255'],
            'contact_name'             => ['nullable', 'string', 'max:255'],
            'company_name'             => ['nullable', 'string', 'max:255'],
            'email'                    => ['nullable', 'email'],
            'phone'                    => 'nullable',
            'identity_document_number' => ['nullable', 'string'],
            'identity_document_type'   => ['nullable', 'string'],
            'state'                    => ['sometimes', 'required', Rule::enum(ShopStateEnum::class)],
            'type'                     => ['required', Rule::enum(ShopTypeEnum::class)],
            'country_id'               => ['required', 'exists:countries,id'],
            'currency_id'              => ['required', 'exists:currencies,id'],
            'language_id'              => ['required', 'exists:languages,id'],
            'timezone_id'              => ['required', 'exists:timezones,id'],
            'settings'                 => ['sometimes', 'array'],
            'warehouses'               => ['sometimes', 'array'],
            'warehouses.*'             => [Rule::Exists('warehouses', 'id')->where('organisation_id', $this->organisation->id)],
            'address'                  => ['sometimes', 'required', new ValidAddress()],

        ];

        if (!$this->strict) {
            $rules['source_id']  = ['sometimes', 'string', 'max:64'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['created_at'] = ['sometimes', 'date'];
            $rules['closed_at']  = ['sometimes', 'nullable', 'date'];
        }

        return $rules;
    }
}
