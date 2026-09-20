<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait WithChatAgentAuthorisation
{
    /**
     * The chat agent profile of the authenticated user, but only when that user may work
     * chat on the shop the session belongs to. The profile is created on first use, so
     * holding the customer service position is the only thing anybody has to set up.
     */
    protected function getAuthorisedChatAgent(ChatSession|MetaChatSession $chatSession): ?ChatAgent
    {
        $user = Auth::user();
        $shop = $chatSession->shop;

        if (!$user instanceof User || !$shop instanceof Shop) {
            return null;
        }

        if (!$this->userCanWorkChatOnShop($user, $shop)) {
            return null;
        }

        return $this->chatAgentProfileFor($user);
    }

    protected function userCanWorkChatOnShop(User $user, Shop $shop): bool
    {
        if ($user->authTo(["crm.{$shop->id}"])) {
            return true;
        }

        // ponytail: shop_has_chat_agents is a second permission system being retired; it still
        // grants while the customer service positions catch up. Every use is logged so the
        // branch, and the table, can go when the log falls silent.
        if ($user->chatAgent?->isAssignedToShop($shop->id, $shop->organisation_id)) {
            Log::warning('chat_legacy_agent_grant', [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'missing' => "crm.{$shop->id}",
            ]);

            return true;
        }

        return false;
    }

    protected function userCanViewChatOnShop(User $user, Shop $shop): bool
    {
        return $this->userCanWorkChatOnShop($user, $shop)
            || $user->authTo(["crm.{$shop->id}.view"]);
    }

    protected function userCanWorkChatOnOrganisation(User $user, Organisation $organisation): bool
    {
        $permissions = $organisation->shops()->pluck('shops.id')
            ->map(fn ($shopId) => "crm.{$shopId}")
            ->all();

        if ($permissions && $user->authTo($permissions)) {
            return true;
        }

        return (bool) $user->chatAgent?->shopAssignments()
            ->whereNull('deleted_at')
            ->where('organisation_id', $organisation->id)
            ->exists();
    }

    protected function chatAgentProfileFor(User $user): ChatAgent
    {
        $agent = ChatAgent::withTrashed()->firstOrNew(['user_id' => $user->id]);

        if ($agent->exists) {
            if ($agent->trashed()) {
                $agent->restore();
            }

            return $agent;
        }

        $agent->fill([
            'max_concurrent_chats' => 10,
            'language_id'          => $user->language_id,
            'is_online'            => false,
            'is_available'         => true,
            'current_chat_count'   => 0,
            'specialization'       => [],
            'auto_accept'          => false,
        ])->save();

        return $agent;
    }
}
