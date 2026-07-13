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

        $organisationCode = $user->employedInOrganisation?->code;

        return [
            'id'                   => $user->id,
            'username'             => $user->username,
            'email'                => $user->email,
            'contact_name'         => $user->contact_name,
            'status'               => $user->status,
            'organisation_code'    => $organisationCode,
            'contact_and_org_code' => $organisationCode
                ? "{$organisationCode} | {$user->contact_name}"
                : (string) $user->contact_name,
        ];
    }
}
