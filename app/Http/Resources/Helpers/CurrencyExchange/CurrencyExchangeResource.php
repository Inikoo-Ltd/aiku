<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:50:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers\CurrencyExchange;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $currency
 * @property string $exchange
 * @property string $date
 */
class CurrencyExchangeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'currency' => $this->currency,
            'exchange' => $this->exchange,
            'date' => $this->date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
