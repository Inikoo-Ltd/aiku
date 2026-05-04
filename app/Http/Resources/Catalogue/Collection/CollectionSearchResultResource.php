<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue\Collection;

use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class CollectionSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Collection $collection */
        $collection = $this;

        return [
            'id'    => $collection->id,
            'code'  => $collection->code,
            'name'  => $collection->name,
            'image' => Arr::get($collection->web_images, 'main.thumbnail'),

        ];
    }
}
