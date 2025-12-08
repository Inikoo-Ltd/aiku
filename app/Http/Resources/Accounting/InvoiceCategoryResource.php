<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-11h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'state' => $this->state,
            'type' => $this->type,
        ];
    }
}
