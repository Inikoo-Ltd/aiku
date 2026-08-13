<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $stock
 * @property mixed $family_code
 * @property mixed $family_slug
 */
class OrgStockLowStockAuditsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'code'        => $this->code,
            'name'        => $this->name,
            'family_code' => $this->family_code,
            'family_slug' => $this->family_slug,
            'stock'       => trimDecimalZeros($this->stock),
        ];
    }
}
