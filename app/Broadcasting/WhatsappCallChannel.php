<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Broadcasting;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\SysAdmin\User;

class WhatsappCallChannel
{
    use WithChatAgentAuthorisation;

    /**
     * A shop's WhatsApp calls ring for the people holding its customer service position.
     */
    public function join($user, string $shopId): bool
    {
        return $user instanceof User && $this->userIsCustomerServiceOnShop($user, (int) $shopId);
    }
}
