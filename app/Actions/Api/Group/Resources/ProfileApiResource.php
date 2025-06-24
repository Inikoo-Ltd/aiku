<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Jun 2025 01:11:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Group\Resources;

use App\Http\Resources\HumanResources\EmployeeResource;
use App\Http\Resources\SysAdmin\GuestResource;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ProfileApiResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var User $user */
        $user = $this;

        return [
            'id'           => $user->id,
            'username'     => $user->username,
            'avatar'       => $user->imageSources(320, 320),
            'contact_name' => $user->contact_name,
            'email'        => $user->email,
            'about'        => $user->about,
            'status'       => $user->status,
            'employee'     => EmployeeResource::collection($user->employees),
            'guest'        => GuestResource::collection($user->guests),
            'created_at'   => $user->created_at,
            'updated_at'   => $user->updated_at,

        ];
    }
}
