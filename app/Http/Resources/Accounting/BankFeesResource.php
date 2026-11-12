<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $month
 * @property string $currency_code
 * @property string $bank_fees
 * @property string $bank_base
 * @property string $absorbed_fees
 */
class BankFeesResource extends JsonResource
{
    public function toArray($request): array
    {
        $base = (float)$this->bank_base;

        return [
            'id'                  => $this->id,
            'month'               => $this->month,
            'currency_code'       => $this->currency_code,
            'bank_fees'           => (float)$this->bank_fees,
            'bank_base'           => $base,
            'bank_fee_percentage' => $base > 0 ? round((float)$this->bank_fees / $base * 100, 2) : null,
            'absorbed_fees'       => (float)$this->absorbed_fees,
        ];
    }
}
