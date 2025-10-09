<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 21:10:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use App\Http\Resources\HasSelfCall;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class LoggedUserResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this;

        return [
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => $user->email,
            'settings' => [
                'app_theme' => Arr::get($user->settings, 'app_theme'),
                'hide_logo' => Arr::get($user->settings, 'hide_logo', false),
            ]
        ];
    }
}
