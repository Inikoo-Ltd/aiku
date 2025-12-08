<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:46:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class UserSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var \App\Models\SysAdmin\User $user */
        $user = $this;

        return [
            'username' => $user->username,
            'image' => $user->image_id,
            'email' => $user->email,
            'contact_name' => $user->contact_name,
            'route' => [
                'name' => 'grp.sysadmin.users.show',
                'parameters' => $user->username,
            ],
            'icon' => ['fal', 'fa-terminal'],
        ];
    }
}
