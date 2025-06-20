<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Mar 2024 14:47:21 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin\Organisation;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_employees_state_working
 * @property int $number_shops_state_open
 * @property mixed $number_customers
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $type
 * @property mixed $code
 */
class OrganisationsApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'name'                           => $this->name,
            'type'                           => $this->type,
            'code'                           => $this->code,
        ];
    }
}
