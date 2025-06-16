<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 01:54:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\MitSavedCard;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum;
use App\Models\Accounting\MitSavedCard;
use Illuminate\Validation\Rule;

class UpdateMitSavedCard extends OrgAction
{
    use WithActionUpdate;

    public function handle(MitSavedCard $mitSavedCard, array $modelData): MitSavedCard
    {
        return $this->update($mitSavedCard, $modelData);
    }

    public function rules(): array
    {
        return [
            'token'            => ['sometimes', 'string'],
            'last_four_digits' => ['sometimes', 'string', 'max:4'],
            'card_type'        => ['sometimes', 'string'],
            'expires_at'       => ['sometimes', 'date'],
            'label'            => ['sometimes', 'string'],
            'state'            => ['sometimes', Rule::enum(MitSavedCardStateEnum::class)],
            'priority'         => ['sometimes', 'integer'],
            'data'             => ['sometimes', 'array'],
            'processed_at'     => ['sometimes', 'date'],
            'failure_status'   => ['sometimes', 'nullable','string'],
        ];
    }

    public function asAction(MitSavedCard $mitSavedCard, array $modelData): MitSavedCard
    {
        $this->asAction = true;
        $this->initialisationFromShop($mitSavedCard->shop, $modelData);

        return $this->handle($mitSavedCard, $modelData);
    }


}
