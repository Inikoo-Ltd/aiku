<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 10:42:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin\User;

use App\Models\SysAdmin\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class GerUsersFromSearchResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var User $user */
        $user = $this;
        return [
            'username'           => $user->username,
            'email'              => $user->email,
            'contact_name'       => $user->contact_name,
            'status'             => $user->status,
        ];
    }
}
