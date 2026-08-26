<?php

/*
 * Author Louis Perez
 * Created on 26-08-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class FamiliesForCategoryComparison extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'category_comparison'   => $this->category_comparison,
            'web_images'            => $this->web_images,
        ];
    }
}
