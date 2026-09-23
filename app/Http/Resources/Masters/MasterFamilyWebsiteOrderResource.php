<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property mixed $web_images
 * @property int|null $website_position
 * @property mixed $masterSubDepartment
 * @property mixed $stats
 * @property mixed $created_at
 */
class MasterFamilyWebsiteOrderResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'image_thumbnail'      => is_array($this->web_images) ? $this->web_images : json_decode($this->web_images),
            'position'             => $this->website_position,
            'sub_department_name'  => $this->masterSubDepartment?->name,
            'created_at'           => $this->created_at,
            'number_products'      => (int) $this->stats?->number_current_master_assets_type_product,
        ];
    }
}
