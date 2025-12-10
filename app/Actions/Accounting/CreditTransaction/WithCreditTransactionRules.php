<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Dec 2025 14:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (JetBrains AI)
 * Created: Mon, 08 Dec 2025
 */

namespace App\Actions\Accounting\CreditTransaction;

use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use Illuminate\Validation\Rule;

trait WithCreditTransactionRules
{
    public function rules(): array
    {
        $rules = [
            'amount'     => ['required', 'numeric'],
            'date'       => ['sometimes', 'date'],
            'type'       => ['required', Rule::enum(CreditTransactionTypeEnum::class)],
            'reason'     => ['required', Rule::enum(CreditTransactionReasonEnum::class)],
            'notes'      => ['sometimes'],
            'payment_id' => [
                'sometimes',
                'nullable',
                Rule::exists('payments', 'id')
                    ->where('shop_id', $this->shop->id)
            ],
            'top_up_id'  => [
                'sometimes',
                'nullable',
                Rule::exists('top_ups', 'id')
                    ->where('shop_id', $this->shop->id)
            ],
        ];

        if (!$this->strict) {
            $rules['grp_exchange'] = ['sometimes', 'numeric'];
            $rules['org_exchange'] = ['sometimes', 'numeric'];
            $rules                 = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }
}
