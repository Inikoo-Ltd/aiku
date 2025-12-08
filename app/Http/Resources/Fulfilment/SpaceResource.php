<?php

/*
 * author Arya Permana - Kirin
 * created on 30-01-2025-16h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\RecurringBill;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $customer_name
 * @property string $customer_slug
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $customer_reference
 * @property mixed $number_pallets
 * @property mixed $estimated_delivery_date
 * @property mixed $state
 */
class SpaceResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        // dd($this->current_recurring_bill_id);
        $recurringBill = RecurringBill::find($this->current_recurring_bill_id);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'state' => $this->state,
            'state_label' => $this->state->labels()[$this->state->value],
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            // 'rental_slug'                => $this->rental_slug,
            'exclude_weekend' => $this->exclude_weekend,
            'rental' => $this->rental,
            'recurring_bill' => RecurringBillResource::make($recurringBill ?? null),
            // 'rental_code'                => $this->rental_code,
        ];
    }
}
