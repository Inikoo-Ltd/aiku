<?php

/*
 * Author: Vika Aqordi
 * Created on 09-02-2026-16h-33m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $slug
 */
class PickedBaysResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
        ];
    }
}
