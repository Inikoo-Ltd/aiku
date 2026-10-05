<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Pupil\Chat;

use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Chat\ChatSession;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePupilChatSession
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData = []): ChatSession
    {
        $shop = GetPupilChatShop::run($shopifyUser);

        if (!$shop) {
            abort(403, 'This shop has no chat');
        }

        /*
         * The merchant is signed in to Shopify, not to Retina, so the conversation is bound to
         * their web user here rather than from the request: the public chat endpoints refuse a
         * claimed web user id for exactly that reason, and everything an agent is shown about
         * the customer follows the binding. A merchant who has not linked an account yet has no
         * web user, and writes as a guest.
         */
        $webUser = $shopifyUser->customer?->webUsers()->where('status', true)->first();

        return StoreChatSession::make()->handle([
            'shop_id'             => $shop->id,
            'language_id'         => $shopifyUser->language_id,
            'priority'            => ChatPriorityEnum::NORMAL->value,
            'channel'             => ChatChannelEnum::WEBSITE->value,
            'trusted_web_user_id' => $webUser?->id,
            'guest_identifier'    => $webUser ? null : 'shopify_'.$shopifyUser->id,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): ChatSession
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $request->user('pupil');

        return $this->handle($shopifyUser, $request->all());
    }

    public function jsonResponse(ChatSession $chatSession): ChatSessionResource
    {
        return ChatSessionResource::make($chatSession)
            ->additional([
                'success' => true,
                'message' => 'Chat session started successfully',
            ]);
    }
}
