<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatCustomerPresence;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The website chat widget beats while the customer has one of the shop's pages open, and says
 * when they close it, so the agent sees whether the customer is still there or has left.
 * ponytail: every beat is broadcast; send only changes if soketi traffic ever matters.
 */
class TrackChatCustomerPresence
{
    use AsAction;

    public const int HEARTBEAT_SECONDS = 60;

    private const int REMEMBER_HOURS = 24;

    public function rules(): array
    {
        return [
            'left' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return bool false when the conversation no longer takes beats, so the widget stops sending them
     */
    public function handle(ChatSession $chatSession, bool $left): bool
    {
        if ($chatSession->status === ChatSessionStatusEnum::CLOSED || $chatSession->channel !== ChatChannelEnum::WEBSITE) {
            return false;
        }

        $presence = ['seen_at' => now()->toISOString(), 'left' => $left];

        Cache::put(self::key($chatSession), $presence, now()->addHours(self::REMEMBER_HOURS));
        BroadcastChatCustomerPresence::dispatch($chatSession->ulid, $presence);

        return true;
    }

    /**
     * @return array{seen_at: string, left: bool}|null
     */
    public static function current(ChatSession $chatSession): ?array
    {
        return $chatSession->channel === ChatChannelEnum::WEBSITE ? Cache::get(self::key($chatSession)) : null;
    }

    private static function key(ChatSession $chatSession): string
    {
        return "chat_customer_presence:$chatSession->id";
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return response()->json([
            'tracking'          => $this->handle($chatSession, $request->boolean('left')),
            'heartbeat_seconds' => self::HEARTBEAT_SECONDS,
        ]);
    }
}
