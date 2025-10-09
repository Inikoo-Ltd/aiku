<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Jun 2024 01:16:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockHistoriesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'layout'      => $this->layout,
            'created_at'  => $this->created_at,
        ];
    }
}
