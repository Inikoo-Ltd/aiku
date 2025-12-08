<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $asset_code
 * @property mixed $asset_name
 * @property mixed $asset_type
 * @property mixed $asset_price
 * @property mixed $asset_units
 * @property mixed $asset_unit
 * @property mixed $percentage_off
 */
class CustomerBalanceTransactionsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'type' => $this->type ? $this->type->label() : null,
            'notes' => $this->notes,
            'date' => $this->date,
            'amount' => $this->amount,
            'running_amount' => $this->running_amount,
            'currency_code' => $this->currency_code,
        ];
    }
}
