<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatPhoneCall;
use App\Models\SysAdmin\User;

trait WithChatPhoneCall
{
    use WithChatAgentAuthorisation;

    protected function activeCallFor(ChatAgent $agent): ?ChatPhoneCall
    {
        return ChatPhoneCall::where('chat_agent_id', $agent->id)
            ->inProgress()
            ->latest('started_at')
            ->first();
    }

    /**
     * What the browser is handed on every call: enough for the timer to survive a reload and for
     * the shop the call is filed against to be corrected without leaving the modal.
     *
     * @return array<string, mixed>
     */
    protected function callPayload(?ChatPhoneCall $call, User $user): array
    {
        return [
            'call'  => $call ? [
                'id'              => $call->id,
                'status'          => $call->status->value,
                'shop_id'         => $call->shop_id,
                'started_at'      => $call->started_at->toIso8601String(),
                'elapsed_seconds' => $call->elapsedSeconds(),
                'contact_type'    => $call->contact_type?->value,
                'customer_id'     => $call->customer_id,
                'chat_session_id' => $call->chat_session_id,
                'contact_name'    => $call->contact_name,
                'notes'           => $call->notes,
            ] : null,
            'shops'               => $this->shopOptionsFor($user),
            'max_minutes'         => (int) config('chat.phone_call.max_minutes'),
            'warn_before_minutes' => (int) config('chat.phone_call.warn_before_minutes'),
        ];
    }

    /**
     * The shops the call can be filed against are the ones this agent works, which is the same
     * list their inbox is built from.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function shopOptionsFor(User $user): array
    {
        $shopIds = $this->workableShopIdsFor($user);

        if ($shopIds === []) {
            return [];
        }

        return Shop::whereIn('id', $shopIds)
            ->orderBy('name')
            ->get(['id', 'name', 'organisation_id'])
            ->map(fn (Shop $shop) => [
                'id'              => $shop->id,
                'name'            => $shop->name,
                'organisation_id' => $shop->organisation_id,
            ])
            ->all();
    }

    protected function assertShopIsWorkable(User $user, ?int $shopId): ?Shop
    {
        if (!$shopId) {
            return null;
        }

        return in_array($shopId, $this->workableShopIdsFor($user), true)
            ? Shop::find($shopId)
            : null;
    }

    protected function closeCall(ChatPhoneCall $call, ChatPhoneCallStatusEnum $status, array $attributes = []): ChatPhoneCall
    {
        $endedAt = now();

        $call->update(array_merge($attributes, [
            'status'           => $status,
            'ended_at'         => $endedAt,
            'duration_seconds' => (int) $call->started_at->diffInSeconds($endedAt, absolute: true),
        ]));

        return $call->refresh();
    }
}
