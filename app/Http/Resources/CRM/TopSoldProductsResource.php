<?php

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $total_sold
 * @property mixed $total_amount
 */
class TopSoldProductsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'total_sold'    => (float) $this->total_sold,
            'total_amount'  => (float) $this->total_amount,
            'currency_code' => $this->currency_code,
        ];
    }
}
