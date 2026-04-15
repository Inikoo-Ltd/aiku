<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 03:53:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin\Guest;

use App\Models\SysAdmin\Guest;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class GuestSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Guest $guest */
        $guest = $this;

        return [
            'id'           => $guest->id,
            'slug'         => $guest->slug,
            'code'         => $guest->code,
            'contact_name' => $guest->contact_name,
            'email'        => $guest->email,
        ];
    }
}
