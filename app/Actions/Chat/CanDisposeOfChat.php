<?php

/*
 * Author: Louis Perez
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CanDisposeOfChat
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(?User $user, ChatSession|MetaChatSession $chatSession): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $this->userCanDisposeOfChat($user, $chatSession);
    }
}
