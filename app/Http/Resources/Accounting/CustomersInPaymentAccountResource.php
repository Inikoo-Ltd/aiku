<?php

namespace App\Http\Resources\Accounting;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomersInPaymentAccountResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $data = [
            'slug'                            => $this->slug,
            'reference'                       => $this->reference,
            'name'                            => $this->name,
            'number_payments'                 => $this->total_payments,
            'total_amount_paid'               => $this->total_amount_paid,
            'currency_code'                   => $this->currency_code,
        ];
        
        return $data;
    }
}
