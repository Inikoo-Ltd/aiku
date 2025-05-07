<?php
/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-14h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
 */
class TopUpsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                  => $this->slug,    
            'reference'                => $this->reference,
            'amount'                => $this->amount,
            'status'                => $this->status
        ];
    }
}
