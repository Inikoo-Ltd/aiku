<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Oct 2024 14:36:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Web\WebBlock;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockParametersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var WebBlock $webBlock */
        $webBlock = $this;

        return [
            'id' => $webBlock->id,
            'products' => ProductResource::collection($webBlock->products),
        ];
    }
}
