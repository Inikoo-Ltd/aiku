<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-10h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Http\Resources\Traits\HasRetinaCustomerProductData;

class RetinaCustomerBackInStockRemindersResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;
    use HasRetinaCustomerProductData;

    public function toArray($request): array
    {
        return $this->getCustomerProductData($request);
    }
}
