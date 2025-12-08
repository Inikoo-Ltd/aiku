<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Nov 2023 15:38:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

class ProspectQueriesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\Helpers\Query $query */
        $query = $this;

        return [
            'slug' => $query->slug,
            'name' => $query->name,
            'number_items' => $query->number_items,
            'constrains' => $query->constrains,
            'has_arguments' => $query->has_arguments,
            'is_seeded' => $query->is_seeded,
            'state' => $query->state,
        ];
    }
}
