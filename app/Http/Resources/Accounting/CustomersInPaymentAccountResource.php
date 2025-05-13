<?php

namespace App\Http\Resources\Accounting;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $reference
 * @property string $name
 * @property mixed $total_payments
 * @property mixed $total_amount_paid
 * @property string $currency_code
 * @property string $shop_code
 * @property string $shop_name
 */
class CustomersInPaymentAccountResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'name'              => $this->name,
            'total_payments'    => $this->total_payments,
            'total_amount_paid' => $this->total_amount_paid,
            'currency_code'     => $this->currency_code,
            'shop_code'         => $this->shop_code,
            'shop_name'         => $this->shop_name,
        ];
    }
}
