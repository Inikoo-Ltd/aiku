<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Jun 2024 01:16:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\WebBlock;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var WebBlock $webBlock */
        $webBlock = $this;

        return [
            'id' => $webBlock->id,
            'layout' => $webBlock->layout,
            'data' => $webBlock->data,
        ];
    }
}
