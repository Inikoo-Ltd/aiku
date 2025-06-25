<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProfileShowcase
{
    use AsObject;

    public function handle(User $user): array
    {
        return [
            'id'          => $user->id,
            'username'    => $user->username,
            'avatar'      => $user->imageSources(48, 48),
            'email'       => $user->email,
            'about'       => $user->about,
            'status'      => match ($user->status) {
                true => [
                    'tooltip' => __('active'),
                    'icon'    => 'fal fa-check',
                    'class'   => 'text-green-500'
                ],
                default => [
                    'tooltip' => __('suspended'),
                    'icon'    => 'fal fa-times',
                    'class'   => 'text-red-500'
                ]
            },
            'roles'       => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
        ];
    }
}
