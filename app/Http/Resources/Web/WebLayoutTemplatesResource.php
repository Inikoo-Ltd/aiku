<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use Illuminate\Http\Resources\Json\JsonResource;

class WebLayoutTemplatesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => $this->type,
            'type'         => $this->sub_type,
            'scope'        => $this->scope,
            'blocks_count' => count($this->blocks ?? []),
            'author_name'  => $this->username,
            'created_at'   => $this->created_at,
            'username'     => $this->username,
        ];
    }
}
