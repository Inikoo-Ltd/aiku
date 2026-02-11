<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $method
 * @property int $number_payments
 * @property float $total_sales
 * @property int $number_success
 * @property float $success_rate
 * @property string $currency_code
 */
class PaymentMethodsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'method'           => $this->method,
            'number_payments'  => (int) $this->number_payments,
            'total_sales'      => number_format((float) $this->total_sales, 2, '.', ''),
            'number_success'   => (int) $this->number_success,
            'success_rate'     => number_format((float) $this->success_rate, 2, '.', ''),
            'currency_code'    => $this->currency_code,
        ];
    }
}
