<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $expiry_date
 */
class BatchCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'expiry_date' => $this->expiry_date,
            'label'       => $this->code.($this->expiry_date ? ' — exp: '.$this->expiry_date->format('d M Y') : ''),
        ];
    }
}
