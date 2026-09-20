<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            || $user->authTo(["chat-m.{$shop->id}"])
            || $this->holdsFulfilmentPermission($user, $shop, 'fulfilment-chat-m')
            // Administering an organisation carries chat across every one of its shops,
            // including any opened later: the permission is held on the organisation, so
            // there is nothing to grant per shop.
            || $user->authTo(["org-admin.{$shop->organisation_id}"]);
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

        if ($this->holdsFulfilmentPermission($user, $shop, 'fulfilment-chat')) {
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

        if ($user->authTo(["org-admin.{$organisation->id}"])) {
            return true;
        }

        $permissions = $organisation->shops()->pluck('shops.id')
            ->flatMap(fn ($shopId) => ["chat.{$shopId}", "chat-m.{$shopId}"])
            ->merge(
                $organisation->fulfilments()->pluck('fulfilments.id')
                    ->flatMap(fn ($id) => ["fulfilment-chat.{$id}", "fulfilment-chat-m.{$id}"])
            )
            ->all();

        if ($permissions && $user->authTo($permissions)) {
            return true;
        }

        return (bool) $user->chatAgent?->shopAssignments()
            ->whereNull('deleted_at')
            ->where('organisation_id', $organisation->id)
            ->exists();
    }

    /**
     * A fulfilment shop staffs its chat from the fulfilment positions, whose permissions
     * are numbered by the fulfilment rather than the shop.
     */
    private function holdsFulfilmentPermission(User $user, Shop $shop, string $permission): bool
    {
        $fulfilmentId = $shop->fulfilment?->id;

        return $fulfilmentId && $user->authTo(["{$permission}.{$fulfilmentId}"]);
    }

    /**
     * The shops somebody works, across every organisation, since chat is not scoped to one:
     * an agent covers whatever their positions give them. This is the one answer to "whose
     * conversations are these", so a list scoped any other way will disagree with the inbox.
     *
     * Read from the permission names rather than shop by shop, because the logged in user's
     * props carry it on every request.
     *
     * @return array<int, int>
     */
    protected function workableShopIdsFor(User $user): array
    {
        if (!$user->status) {
            return [];
        }

        $names = DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', 'User')
            ->where('model_has_roles.model_id', $user->id)
            ->where(function ($query) {
                $query->where('permissions.name', 'like', 'chat.%')
                    ->orWhere('permissions.name', 'like', 'fulfilment-chat.%');
            })
            ->distinct()
            ->pluck('permissions.name');

        $shopByFulfilment = null;
        $shopIds          = [];

        foreach ($names as $name) {
            // chat.12.view is a permission to read, never to work, so only the bare form counts.
            if (preg_match('/^chat\.(\d+)$/', $name, $match)) {
                $shopIds[] = (int) $match[1];

                continue;
            }

            if (preg_match('/^fulfilment-chat\.(\d+)$/', $name, $match)) {
                $shopByFulfilment ??= Fulfilment::pluck('shop_id', 'id');
                $shopId             = $shopByFulfilment[(int) $match[1]] ?? null;

                if ($shopId) {
                    $shopIds[] = (int) $shopId;
                }
            }
        }

        // ponytail: the retired assignment table still counts while the positions catch up,
        // same as userCanWorkChatOnShop. Goes with that branch.
        $legacy = $user->chatAgent?->shopAssignments()->whereNull('deleted_at')->pluck('shop_id')->all() ?? [];

        return array_values(array_unique(array_merge($shopIds, $legacy)));
    }

    /**
     * What a list of conversations may be asked for by the person asking. The shops are limited
     * to the ones they may look at, and "mine" always means theirs: both used to be taken from
     * the request, so anybody signed in could read any shop's conversations by naming it, or a
     * colleague's by naming them.
     *
     * Applied where the request comes in, never in handle(), which our own code calls for a
     * customer's history and has already decided whose it is.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function chatFiltersScopedTo(mixed $user, array $filters): array
    {
        if (!$user instanceof User) {
            return [...$filters, 'allowed_shop_ids' => []];
        }

        if (!empty($filters['assigned_to_me'])) {
            $filters['assigned_to_me'] = $user->id;
        }

        $shops = Shop::with('fulfilment')
            ->when(!empty($filters['shop_id']), fn ($query) => $query->where('id', (int) $filters['shop_id']))
            ->get();

        $filters['allowed_shop_ids'] = $shops
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($user, $shop))
            ->pluck('id')
            ->all();

        return $filters;
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

    /**
     * @return array<int, int>
     */
    protected function shopIdsWorkedBy(int $userId): array
    {
        $user = User::find($userId);

        return $user ? $this->workableShopIdsFor($user) : [];
    }

    /**
     * The colleagues on the same shops, so the team tab shows the conversations somebody else
     * is holding on a shop this person also works.
     *
     * @param  array<int, int>  $shopIds
     * @return array<int, int>
     */
    protected function agentIdsCovering(array $shopIds, int $exceptAgentId): array
    {
        return ChatAgent::with('user')->where('id', '!=', $exceptAgentId)->get()
            ->filter(fn (ChatAgent $agent) => $agent->user
                && array_intersect($shopIds, $this->workableShopIdsFor($agent->user)) !== [])
            ->pluck('id')
            ->all();
    }
}
