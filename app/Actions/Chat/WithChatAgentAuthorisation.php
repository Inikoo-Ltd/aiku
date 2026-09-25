<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     * A conversation somebody is holding is theirs to dispose of: putting it aside, reporting
     * the sender or raising a ticket off it are the assignee's calls, not a passing colleague's.
     * Supervisors keep the override, or a chat left behind by an absent agent could never be
     * cleared without taking it over first.
     */
    protected function userCanDisposeOfChat(User $user, ChatSession|MetaChatSession $chatSession): bool
    {
        $shop = $chatSession->shop;

        if (!$shop instanceof Shop || !$this->userCanActOnChatOnShop($user, $shop)) {
            return false;
        }

        $assigneeUserId = $chatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->latest('id')
            ->first()?->chatAgent?->user_id;

        if (!$assigneeUserId || $assigneeUserId === $user->id) {
            return true;
        }

        return $this->userSupervisesChatOnShop($user, $shop);
    }

    protected function chatHeldByAnotherAgentMessage(ChatSession|MetaChatSession $chatSession): string
    {
        $assignee = $chatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->latest('id')
            ->first()?->chatAgent?->user?->contact_name;

        return $assignee
            ? __(':agent is handling this chat. Take it over first, or ask a supervisor.', ['agent' => $assignee])
            : __('Another agent is handling this chat. Take it over first, or ask a supervisor.');
    }

    protected function userSupervisesChatOnShop(User $user, Shop $shop): bool
    {
        if (!$user->status) {
            return false;
        }

        return $user->authTo(["chat-m.{$shop->id}"])
            || $this->holdsFulfilmentPermission($user, $shop, 'fulfilment-chat-m')
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

        return $this->holdsFulfilmentPermission($user, $shop, 'fulfilment-chat');
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

        return (bool) ($permissions && $user->authTo($permissions));
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

        return array_values(array_unique($shopIds));
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
            ->when(!empty($filters['shop_ids']), fn ($query) => $query->whereIn('id', array_map('intval', (array) $filters['shop_ids'])))
            ->get();

        $filters['allowed_shop_ids'] = $shops
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($user, $shop))
            ->pluck('id')
            ->all();

        // The unclaimed queue is deliberately not scoped to the shops this person works. A
        // conversation reaching a queue nobody watching could answer is the whole failure it
        // exists to catch, so it is the group's queue and anybody who works chat at all sees
        // every conversation in it, whichever shop or organisation it arrived on.
        if (!empty($filters['unclaimed']) && $filters['allowed_shop_ids'] !== []) {
            unset($filters['allowed_shop_ids']);
        }

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
     * The conversations a colleague is holding on the shops this person works. Membership is
     * read from the conversation, never from the colleague's own positions: those change, and
     * when they do the tab emptied while the rail still counted the chats.
     *
     * Closed conversations nobody ever picked up belong here too. They are the shop's history
     * and were in no list at all.
     */
    protected function scopeHeldByColleague($query, int $exceptAgentId, string $assignmentStatus, bool $includeUnheld = false): void
    {
        $query->where(function ($outer) use ($exceptAgentId, $assignmentStatus, $includeUnheld) {
            $outer->whereHas('assignments', fn ($a) => $a->where('chat_agent_id', '!=', $exceptAgentId)
                ->where('status', $assignmentStatus));

            if ($includeUnheld) {
                $outer->orWhereDoesntHave('assignments', fn ($a) => $a->where('status', $assignmentStatus));
            }
        });
    }
}
