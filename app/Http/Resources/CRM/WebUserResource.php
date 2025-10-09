<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:46:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Models\CRM\WebUser;
use Illuminate\Http\Resources\Json\JsonResource;

class WebUserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var WebUser $webUser */
        $webUser = $this;

        return [
            'slug'       => $webUser->slug,
            'username'   => $webUser->username,
            'contact_name' => $webUser->contact_name,
            'status'     => $webUser->status,
            'email'      => $webUser->email,
            'is_root'    => $webUser->is_root,
            'last_login' => $webUser->stats->last_login_at ?? 'no data',
            'created_at' => $webUser->created_at,
            'updated_at' => $webUser->updated_at,
            'customer'   => CustomersResource::make($webUser->customer),
            'delete_route' => [
                'name' => 'grp.models.web-user.delete',
                'parameters' => [
                    'webUser' => $this->id
                ]
            ]
        ];
    }
}
