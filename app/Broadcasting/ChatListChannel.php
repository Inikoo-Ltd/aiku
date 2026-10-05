<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Broadcasting;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;

class ChatListChannel
{
    use WithChatAgentAuthorisation;

    /**
     * The shop's live chat list goes to whoever works its chat, by the same rule that puts the
     * shop in their layout's agent shops. Reading only the retired assignment table left
     * agents made by their position subscribed to nothing, so no customer ever rang for them.
     */
    public function join($user, string $shopId): array|false
    {
        $shop = Shop::find($shopId);

        if (!$user instanceof User || !$shop || !$this->userCanWorkChatOnShop($user, $shop)) {
            return false;
        }

        return ['id' => $user->id, 'name' => $user->contact_name];
    }
}
