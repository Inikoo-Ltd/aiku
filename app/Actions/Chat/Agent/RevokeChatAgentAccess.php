<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Agent;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RevokeChatAgentAccess
{
    use AsAction;

    public string $commandSignature = 'chat:revoke_lapsed_agents {--dry-run} {--force}';

    public string $commandDescription = 'Release the chats of agents who may no longer work the shop they are on';

    /**
     * Somebody who may no longer work chat on a shop cannot keep holding its conversations:
     * the chats go back to that shop's queue where any agent sees them, and the profile is
     * suspended once there is no shop left it may work. Re-granting the position brings the
     * profile back, because it is created on first use.
     *
     * @return array{released: int, shops_removed: int, suspended: bool}
     */
    public function handle(ChatAgent $agent, bool $dryRun = false): array
    {
        $user = $agent->user;

        if (!$user instanceof User) {
            return ['released' => 0, 'shops_removed' => 0, 'suspended' => false, 'restored' => false];
        }

        // Roles are read through spatie's team scope, and nothing binds it outside a
        // request: without this every permission reads as absent and every agent looks
        // revoked. authTo() binds it on its own, getAllPermissions() does not.
        if ($user->group_id) {
            setPermissionsTeamId($user->group_id);
        }

        // Read the grants straight from spatie. authTo() caches a positive answer for an
        // hour, and a revocation that believes a stale yes is the one case that must not
        // happen: the whole point here is that the permission has just gone away.
        $granted = $user->getAllPermissions();

        $chatShopIds = $granted
            ->map(fn ($permission) => preg_match('/^chat(?:-m)?\.(\d+)$/', $permission->name, $m) ? (int) $m[1] : null)
            ->filter()
            ->values();

        // Administering an organisation carries chat across all of its shops, so nothing
        // an organisation administrator holds is ever lapsed.
        $adminOrgIds = $granted
            ->map(fn ($permission) => preg_match('/^org-admin\.(\d+)$/', $permission->name, $m) ? (int) $m[1] : null)
            ->filter()
            ->values();

        // Fulfilment shops staff chat from their own permissions, numbered by fulfilment.
        $fulfilmentIds = $granted
            ->map(fn ($permission) => preg_match('/^fulfilment-chat(?:-m)?\.(\d+)$/', $permission->name, $m) ? (int) $m[1] : null)
            ->filter()
            ->values();

        if ($fulfilmentIds->isNotEmpty()) {
            $chatShopIds = $chatShopIds->merge(
                Shop::whereIn('id', Fulfilment::whereIn('id', $fulfilmentIds)->pluck('shop_id'))->pluck('id')
            );
        }

        if ($adminOrgIds->isNotEmpty()) {
            $chatShopIds = $chatShopIds->merge(
                Shop::whereIn('organisation_id', $adminOrgIds)->pluck('id')
            );
        }

        $chatShopIds = $chatShopIds->unique()->values()->all();

        $released     = 0;
        $shopsRemoved = 0;

        $assignments = ChatAssignment::with('chatSession.shop')
            ->where('chat_agent_id', $agent->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->get();

        foreach ($assignments as $assignment) {
            $shop = $assignment->chatSession?->shop;

            if ($shop && in_array($shop->id, $chatShopIds, true)) {
                continue;
            }

            if (!$dryRun) {
                $this->release($assignment, $agent);
            }

            $released++;
        }

        foreach ($agent->shopAssignments()->with('shop')->get() as $shopAssignment) {
            $shop = $shopAssignment->shop;

            if ($shop && in_array($shop->id, $chatShopIds, true)) {
                continue;
            }

            if (!$dryRun) {
                $shopAssignment->delete();
            }

            $shopsRemoved++;
        }

        // Regaining the permission brings the profile straight back, rather than leaving
        // somebody missing from the agents list until they next open the inbox.
        $restored = false;

        if ($chatShopIds !== [] && $agent->trashed()) {
            if (!$dryRun) {
                $agent->restore();
            }

            $restored = true;
        }

        $suspended = $this->suspendIfNothingLeft($agent, $chatShopIds, $dryRun);

        if (!$dryRun && $released > 0) {
            ChatAgentHydrateChats::run($agent);
        }

        return ['released' => $released, 'shops_removed' => $shopsRemoved, 'suspended' => $suspended, 'restored' => $restored];
    }

    private function release(ChatAssignment $assignment, ChatAgent $agent): void
    {
        DB::transaction(function () use ($assignment, $agent) {
            $assignment->update([
                'status' => ChatAssignmentStatusEnum::RESOLVED->value,
                'note'   => 'Released: agent may no longer work chat on this shop',
            ]);

            $chatSession = $assignment->chatSession;

            if (!$chatSession) {
                return;
            }

            if ($chatSession->status === ChatSessionStatusEnum::ACTIVE) {
                $chatSession->update(['status' => ChatSessionStatusEnum::WAITING->value]);
            }

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::RELEASED,
                actorType: ChatActorTypeEnum::SYSTEM,
                actorId: null,
                payload: [
                    'from_agent_id'   => $agent->id,
                    'from_agent_name' => $agent->user?->contact_name,
                    'reason'          => 'permission_revoked',
                    'timestamp'       => now()->toISOString(),
                ]
            );

            BroadcastChatListEvent::dispatch(null, $chatSession);
        });
    }

    /**
     * @param  array<int, int>  $chatShopIds
     */
    private function suspendIfNothingLeft(ChatAgent $agent, array $chatShopIds, bool $dryRun): bool
    {
        if ($chatShopIds !== []) {
            return false;
        }

        if (!$dryRun) {
            $agent->delete();
        }

        return true;
    }

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');

        // Always work out what would happen first, so the total wipe check below can run
        // before anything is written.
        $planned = ChatAgent::withTrashed()->with('user')->get()->mapWithKeys(
            fn (ChatAgent $agent) => [$agent->id => $this->handle($agent, true)]
        );

        $total     = $planned->count();
        $suspended = $planned->filter(fn ($result) => $result['suspended'])->count();

        // Suspending every last agent means the permissions are not readable, not that
        // the whole company stopped doing customer service. Refuse rather than empty the
        // inboxes on a bad read.
        if ($suspended === $total && $total > 0 && !$command->option('force')) {
            $command->error("Refusing: every one of the {$total} agents came back revoked, which means the chat permissions are not being read. Check shop:seed-permissions has run, then pass --force if this really is intended.");

            return 1;
        }

        $rows = [];

        foreach (ChatAgent::withTrashed()->with('user')->get() as $agent) {
            $result = $dryRun ? $planned->get($agent->id) : $this->handle($agent);

            if ($result['released'] || $result['shops_removed'] || $result['suspended'] || $result['restored']) {
                $rows[] = [
                    $agent->user?->username ?? $agent->id,
                    $result['released'],
                    $result['shops_removed'],
                    $result['suspended'] ? 'yes' : 'no',
                    $result['restored'] ? 'yes' : 'no',
                ];
            }
        }

        $command->table(['Agent', 'Chats released', 'Shop rows removed', 'Suspended', 'Restored'], $rows);
        $command->info($dryRun ? 'Dry run, nothing written' : 'Done');

        return 0;
    }
}
