<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 21:10:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLoggedUser
{
    use AsObject;

    public function handle(User $user): array
    {

        $isAgent = false;
        $agentShops = [];

        if ($user->chatAgent) {
            $shopAssignments = $user->chatAgent->shopAssignments;
            if ($shopAssignments && $shopAssignments->isNotEmpty()) {
                $isAgent = true;
                $agentShops = $shopAssignments->pluck('shop_id')->unique()->values()->toArray();
            }
        }

        return [
            'id'           => $user->id,
            'username'     => $user->username,
            'contact_name' => (string) $user->contact_name,
            'email'        => $user->email,
            'is_agent'    => $isAgent,
            'agent_shops' => $agentShops,
            'settings' => [
                'timezones' => Arr::get($user->settings, 'timezones'),
                'app_theme' => Arr::get($user->settings, 'app_theme'),
                'hide_logo' => Arr::get($user->settings, 'hide_logo', false),
            ]
        ];
    }
}
