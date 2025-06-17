<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:27:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray($request): array
    {

        /** @var Collection $collection */
        $collection = $this;

        return [
            'id'                => $collection->id,
            'slug'              => $collection->slug,
            'state'             =>  $collection->state,
            'code'              => $collection->code,
            'name'              => $collection->name,
            'description'       => html_entity_decode(strip_tags($collection->description)),
            'created_at'        => $collection->created_at,
            'updated_at'        => $collection->updated_at,



        ];
    }
}
