<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Broadcasting;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;

class MetaChatSessionChannel
{
    use WithChatAgentAuthorisation;

    /**
     * A whatsapp session's channel follows the same rule as everything else in chat: whoever
     * may view the shop's conversations may listen to this one.
     */
    public function join($user, string $ulid): bool
    {
        $metaChatSession = MetaChatSession::where('ulid', $ulid)->first();
        $shop            = $metaChatSession?->shop;

        if (!$user instanceof User || !$shop) {
            return false;
        }

        return $this->userCanViewChatOnShop($user, $shop);
    }
}
