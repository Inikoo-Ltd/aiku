<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\HumanResources\WorkSchedule\GetChatConfig;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatStatus
{
    use AsAction;

    public function handle(Shop $shop, ChatSession $chatSession): array
    {
        $website = $shop->website;

        $isUser     = !empty($chatSession->web_user_id);
        $isMetadata = !empty($chatSession->metadata['name'] ?? null)
            || !empty($chatSession->metadata['email'] ?? null);


        /*
         * An external shop has no website of ours, so its availability is read off the shop
         * itself rather than off a website that will never exist. Without this it is permanently
         * offline and the widget can only ever offer the leave-a-message form.
         */
        $chatConfig = $website
            ? GetChatConfig::run($website)
            : GetChatConfig::make()->forShop($shop, ShopPermissionsEnum::shopHasChat($shop));

        return [
            'is_online'    => $chatConfig['is_online'],
            'schedule'     => $chatConfig['schedule'],
            'offline_info' => $chatConfig['offline_info'],
            'session'      => $chatSession,
            'is_user'      => $isUser,
            'is_metadata'  => $isMetadata,
        ];
    }


    public function asController(ActionRequest $request): JsonResponse
    {
        $shop        = Shop::findOrFail($request->validated('shop_id'));
        $chatSession = ChatSession::where('ulid', $request->validated('ulid'))->firstOrFail();

        $this->refreshWebsiteVisitor($chatSession, $shop->id);

        $config = $this->handle($shop, $chatSession);

        return response()->json([
            'chat_config' => $config
        ]);
    }

    private function refreshWebsiteVisitor(ChatSession $chatSession, int $shopId): void
    {
        $currentVisitorId = null;

        if (request()->hasSession()) {
            $currentVisitorId = WebsiteVisitor::where('session_id', request()->session()->getId())
                ->where('shop_id', $shopId)
                ->latest('id')
                ->value('id');
        }

        if (!$currentVisitorId) {
            $ipHash       = hash('sha256', request()->ip().config('app.key'));
            $visitorHash  = hash('sha256', $ipHash.(request()->userAgent() ?? ''));

            $currentVisitorId = WebsiteVisitor::where('visitor_hash', $visitorHash)
                ->where('shop_id', $shopId)
                ->where('last_seen_at', '>=', now()->subMinutes(30))
                ->latest('id')
                ->value('id');
        }

        if ($currentVisitorId && $chatSession->website_visitor_id !== $currentVisitorId) {
            $chatSession->update(['website_visitor_id' => $currentVisitorId]);
        }
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'ulid' => ['required', 'ulid', 'exists:chat_sessions,ulid'],
        ];
    }
}
