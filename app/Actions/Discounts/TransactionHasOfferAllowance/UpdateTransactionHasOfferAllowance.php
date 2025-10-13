<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 16:01:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\TransactionHasOfferAllowance;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Discounts\TransactionHasOfferAllowance;
use App\Models\Inventory\Location;

class UpdateTransactionHasOfferAllowance extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Location $location;

    public function handle(TransactionHasOfferAllowance $transactionHasOfferComponent, array $modelData): TransactionHasOfferAllowance
    {
        return $this->update($transactionHasOfferComponent, $modelData, ['data']);
    }


    public function rules(): array
    {
        $rules = [
            'is_pinned'             => ['sometimes', 'boolean'],
            'info'                  => ['sometimes', 'nullable', 'string', 'max:10000'],
            'data'                  => ['sometimes', 'nullable', 'array'],
            'discounted_amount'     => ['sometimes', 'required', 'nullable', 'numeric'],
            'discounted_percentage' => ['sometimes', 'required', 'nullable', 'numeric', 'min:0', 'max:1'],

        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(TransactionHasOfferAllowance $transactionHasOfferComponent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): TransactionHasOfferAllowance
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup(group(), $modelData);

        return $this->handle($transactionHasOfferComponent, $this->validatedData);
    }


}
