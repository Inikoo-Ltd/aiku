<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A conversation that reached the wrong shop goes to the queue of the right one, unheld, so its
 * agents pick it up there. An email keeps being answered from the mailbox the customer wrote to,
 * in the same thread: the shop it came from is kept as the conversation's mailbox.
 */
class MoveChatSessionToShop
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatSession $chatSession, Shop $shop, ChatAgent $agent): ChatSession
    {
        $fromShop = $chatSession->shop;

        if ($fromShop?->id === $shop->id) {
            return $chatSession;
        }

        $holders = ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->with('chatAgent')
            ->get();

        DB::transaction(function () use ($chatSession, $shop, $fromShop, $agent, $holders) {
            ChatAssignment::whereIn('id', $holders->pluck('id'))->update([
                'status'      => ChatAssignmentStatusEnum::RESOLVED->value,
                'resolved_at' => now(),
                'note'        => 'Moved to another shop',
            ]);

            $chatSession->update([
                'shop_id'  => $shop->id,
                'status'   => $chatSession->status === ChatSessionStatusEnum::ACTIVE ? ChatSessionStatusEnum::WAITING->value : $chatSession->status->value,
                'metadata' => $this->metadataAfterMove($chatSession, $fromShop, $shop),
            ]);

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::MOVED_TO_SHOP,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: [
                    'moved_by_id'    => $agent->id,
                    'moved_by_name'  => $agent->user?->contact_name,
                    'from_shop_id'   => $fromShop?->id,
                    'from_shop_name' => $fromShop?->name,
                    'to_shop_id'     => $shop->id,
                    'to_shop_name'   => $shop->name,
                ]
            );
        });

        $chatSession->refresh();

        BroadcastChatListEvent::dispatch(null, $chatSession);

        $holders->pluck('chatAgent')->filter()->unique('id')->each(fn (ChatAgent $holder) => ChatAgentHydrateChats::run($holder));

        return $chatSession;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataAfterMove(ChatSession $chatSession, ?Shop $fromShop, Shop $toShop): array
    {
        $metadata = $chatSession->metadata ?? [];

        if ($chatSession->channel !== ChatChannelEnum::EMAIL || !$fromShop) {
            return $metadata;
        }

        $mailboxShopId = (int) (Arr::get($metadata, ProcessInboundEmail::MAILBOX_SHOP_KEY) ?: $fromShop->id);

        if ($mailboxShopId === $toShop->id) {
            return Arr::except($metadata, ProcessInboundEmail::MAILBOX_SHOP_KEY);
        }

        return array_merge($metadata, [ProcessInboundEmail::MAILBOX_SHOP_KEY => $mailboxShopId]);
    }

    /**
     * Any open shop of the conversation's organisation, or any other shop the person works chat
     * on, as long as they may act on chat there: they are putting work in that shop's queue.
     *
     * @return Collection<int, Shop>
     */
    public static function targetShopsFor(User $user, ChatSession $chatSession): Collection
    {
        $currentShopId  = $chatSession->shop_id;
        $organisationId = $chatSession->shop?->organisation_id;

        return self::shopsUserMayMoveTo($user, $organisationId)
            ->reject(fn (Shop $shop) => $shop->id === $currentShopId)
            ->values();
    }

    /**
     * @return Collection<int, Shop>
     */
    private static function shopsUserMayMoveTo(User $user, ?int $organisationId): Collection
    {
        // once() keys an object by spl_object_id, which PHP hands to the next object once this one
        // is freed: the id keeps another user from being given this user's shops.
        $userId = $user->id;

        return once(function () use ($user, $userId, $organisationId) {
            $action = self::make();

            return Shop::with('fulfilment')
                ->where('group_id', $user->group_id)
                ->where('state', ShopStateEnum::OPEN)
                ->where(fn ($query) => $query->where('organisation_id', $organisationId)->orWhereIn('id', $action->workableShopIdsFor($user)))
                ->orderBy('name')
                ->get()
                ->filter(fn (Shop $shop) => $action->userCanActOnChatOnShop($user, $shop))
                ->values();
        });
    }

    public function userMayMove(mixed $user, ChatSession $chatSession): bool
    {
        return $user instanceof User
            && $chatSession->shop instanceof Shop
            && $this->userCanActOnChatOnShop($user, $chatSession->shop)
            && self::targetShopsFor($user, $chatSession)->isNotEmpty();
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $user  = $request->user();
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only agents of this shop can move a conversation')], 403);
        }

        if (!$this->userCanDisposeOfChat($user, $chatSession)) {
            return response()->json(['success' => false, 'message' => $this->chatHeldByAnotherAgentMessage($chatSession)], 403);
        }

        $shopId = (int) $request->validated('shop_id');
        $shop   = self::targetShopsFor($user, $chatSession)->firstWhere('id', $shopId);

        if (!$shop) {
            return response()->json(['success' => false, 'message' => __('You may not move conversations to that shop')], 403);
        }

        $this->handle($chatSession, $shop, $agent);

        return response()->json([
            'success' => true,
            'message' => __('Moved to :shop', ['shop' => $shop->name]),
            'data'    => ['session_ulid' => $chatSession->ulid, 'shop_id' => $shop->id],
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function targetShops(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $chatSession->shop instanceof Shop && $this->userCanActOnChatOnShop($user, $chatSession->shop), 403);

        return response()->json([
            'data' => self::targetShopsFor($user, $chatSession)->map(fn (Shop $shop) => [
                'id'   => $shop->id,
                'code' => $shop->code,
                'name' => $shop->name,
            ])->values()->all(),
        ]);
    }
}
