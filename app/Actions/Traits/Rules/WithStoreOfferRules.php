<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Nov 2025 09:44:33 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Rules;

use App\Rules\IUnique;
use Illuminate\Validation\Rule;

trait WithStoreOfferRules
{
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                new IUnique(
                    table: 'offers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),

                'max:64',
                'alpha_dash',
            ],
            'name' => ['required', 'max:250', 'string'],
            'data' => ['sometimes', 'required'],
            'settings' => ['sometimes', 'required'],
            'trigger_data' => ['sometimes', 'required'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date'],
            'type' => ['required', 'string'],
            'trigger_type' => ['sometimes', Rule::in(['Order'])],
        ];
    }
}
