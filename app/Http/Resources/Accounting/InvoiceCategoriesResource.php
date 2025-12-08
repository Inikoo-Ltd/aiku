<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-10h-04m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceCategoriesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'state' => $this->state,
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'number_type_invoices' => $this->number_type_invoices,
            'number_type_refunds' => $this->number_type_refunds,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'state_label' => $this->state->labels()[$this->state->value],
        ];
    }
}
