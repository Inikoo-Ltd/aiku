<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $group_id
 * @property mixed $master_shop_id
 * @property string $slug
 * @property string $code
 * @property string $description
 * @property bool $status
 * @property mixed $data
 */
class MasterCollectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'code'           => $this->code,
            'description'    => $this->description,
            'status'         => $this->status,
            'data'           => $this->data,
        ];
    }
}
