<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithChatSessionReadAuthorisation
{
    use WithChatAgentAuthorisation;
    use WithUnclaimedChatSessions;

    /**
     * A conversation's customer, timeline, activity, messages and tickets are read by whoever
     * may see chat on the conversation's shop, and, while it sits in the unclaimed queue, by
     * anybody that queue is shown to: it is the whole group's.
     */
    public function authorize(ActionRequest $request): bool
    {
        $chatSession = $request->route('chatSession') ?? $request->route('metaChatSession');
        $shop        = $chatSession?->shop;
        $user        = $request->user();

        if (!$user instanceof User || !$shop instanceof Shop) {
            return false;
        }

        if ($this->userCanViewChatSession($user, $chatSession)) {
            return true;
        }

        $seesUnclaimedQueue = !array_key_exists('allowed_shop_ids', $this->chatFiltersScopedTo($user, ['unclaimed' => true]));

        return $seesUnclaimedQueue && $chatSession::query()
            ->whereKey($chatSession->getKey())
            ->tap(fn ($query) => $chatSession instanceof MetaChatSession ? $this->scopeUnclaimedMetaChatSessions($query) : $this->scopeUnclaimedChatSessions($query))
            ->exists();
    }
}
