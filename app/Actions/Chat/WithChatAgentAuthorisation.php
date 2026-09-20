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

        if (!$this->userCanActOnChatOnShop($user, $shop)) {
            return null;
        }

        return $this->chatAgentProfileFor($user);
    }

    /**
     * Acting on a conversation — taking it over, writing in it, closing it — is open to
     * whoever supervises the shop as well as to its agents. Supervising is not working:
     * a manager is never routed a chat and never counts as an agent.
     */
    protected function userCanActOnChatOnShop(User $user, Shop $shop): bool
    {
        if (!$user->status) {
            return false;
        }

        return $this->userCanWorkChatOnShop($user, $shop)
            || $user->authTo(["chat-m.{$shop->id}"]);
    }

    /**
     * Working chat means being an agent: in the routing pool, in the rota, in the figures.
     */
    protected function userCanWorkChatOnShop(User $user, Shop $shop): bool
    {
        if (!$user->status) {
            return false;
        }

        if ($user->authTo(["chat.{$shop->id}"])) {
            return true;
        }

        // ponytail: shop_has_chat_agents is a second permission system being retired; it still
        // grants while the customer service positions catch up. Every use is logged so the
        // branch, and the table, can go when the log falls silent.
        if ($user->chatAgent?->isAssignedToShop($shop->id, $shop->organisation_id)) {
            Log::warning('chat_legacy_agent_grant', [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'missing' => "chat.{$shop->id}",
            ]);

            return true;
        }

        return false;
    }

    protected function userCanViewChatOnShop(User $user, Shop $shop): bool
    {
        return $this->userCanActOnChatOnShop($user, $shop)
            || $user->authTo(["chat.{$shop->id}.view"]);
    }

    protected function userCanWorkChatOnOrganisation(User $user, Organisation $organisation): bool
    {
        if (!$user->status) {
            return false;
        }

        $permissions = $organisation->shops()->pluck('shops.id')
            ->flatMap(fn ($shopId) => ["chat.{$shopId}", "chat-m.{$shopId}"])
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
