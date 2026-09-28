<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 21:10:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\SysAdmin\User\BorrowUserPermissions;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Helpers\TimeZone\Json\IndexTimeZones;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLoggedUser
{
    use AsObject;
    use WithChatAgentAuthorisation;

    public function handle(User $user): array
    {

        // Being an agent comes from the customer service position, not from the shop assignment
        // table being retired: somebody made an agent by their position was shown no chat in the
        // menu and given no live updates, because the table knew nothing about them.
        $agentShops = $this->workableShopIdsFor($user);
        $isAgent    = $agentShops !== [];

        return [
            'id'           => $user->id,
            'username'     => $user->username,
            'contact_name' => (string) $user->contact_name,
            'nickname'     => $user->nickname,
            'language_id'  => $user->language_id,
            'email'        => $user->email,
            'is_agent'     => $isAgent,
            'borrowed_permissions_from' => $user->permissionsLender()?->only(['id', 'username', 'contact_name']),
            'can_borrow_permissions'    => BorrowUserPermissions::canBorrowSomebody($user),
            'agent_id'     => $user->chatAgent?->id,
            'agent_shops'  => $agentShops,
            'timezone'       => $user->timezone_name,
            'timezone_place' => IndexTimeZones::make()->clockNameFor($user->timezone_name),
            'settings' => [
                'app_theme' => Arr::get($user->settings, 'app_theme'),
                'hide_logo' => Arr::get($user->settings, 'hide_logo', false),
                'alert_sounds' => Arr::get($user->settings, 'alert_sounds'),
                'alert_preview_seconds' => Arr::get($user->settings, 'alert_preview_seconds'),
            ]
        ];
    }
}
