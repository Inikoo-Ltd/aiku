<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 19:24:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service;

use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;

trait WithControllerUpdateServiceRules
{
    public function rules(): array
    {
        return [

            'is_public'   => ['sometimes', 'boolean'],
            'code'        => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'services',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->service->id, 'operator' => '!=']
                    ]
                ),
            ],
            'name'        => ['sometimes', 'required', 'max:250', 'string'],
            'description' => ['sometimes', 'required', 'string', 'max:1500'],
            'unit'        => ['sometimes', 'required', 'string', 'max:255'],
            'fixed_price' => ['sometimes', 'boolean'],
            'price'       => ['sometimes', 'required', 'numeric', 'min:0'],
            'active'      => ['sometimes', 'boolean'],
        ];
    }
}
