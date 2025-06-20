<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 23:29:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\SysAdmin;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\SysAdmin\Group $group */
        $group = $this;

        return [
            'id'         => $group->id,
            'slug'       => $group->slug,
            'code'       => $group->code,
            'name'       => $group->name,
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];
    }
}
