<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Jun 2024 01:16:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\WebBlockType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class WebBlockTypesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var WebBlockType $webBlockType */
        $webBlockType = $this;

        return [
            'id'          => $webBlockType->id,
            'category'    => $webBlockType->category,
            'code'        => $webBlockType->code,
            'scope'       => $webBlockType->scope,
            'name'        => $webBlockType->name,
            'description' => $webBlockType->description,
            'blueprint'   => $webBlockType->blueprint,
            'data'        => $webBlockType->data,
            'created_at'  => $webBlockType->created_at,
            'updated_at'  => $webBlockType->updated_at,
            'screenshot'  => $webBlockType->imageSources(),
            'visibility'  => ['in' => true, 'out' => true],
            'show'        => true,
            'component'   => Arr::get($webBlockType->data, 'component'),
            'icon'        => Arr::get($webBlockType->data, 'icon')

        ];
    }
}
