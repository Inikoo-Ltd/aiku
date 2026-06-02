<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\HumanResources\WorkSchedule\GetChatConfig;
use App\Models\Catalogue\Shop;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Livechat\ChatSession;

class GetChatStatus
{
    use AsAction;

    public function handle(Shop $shop, ChatSession $chatSession): array
    {
        $website = $shop->website;

        $isUser     = !empty($chatSession->web_user_id);
        $isMetadata = !empty($chatSession->metadata['name'] ?? null)
            || !empty($chatSession->metadata['email'] ?? null);


        if (!$website) {
            return [
                'is_online'    => false,
                'schedule'     => null,
                'offline_info' => null,
                'session'      => $chatSession,
                'is_user'      => $isUser,
                'is_metadata'  => $isMetadata,
            ];
        }

        $chatConfig = GetChatConfig::run($website);

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
        $chatSession = ChatSession::where('ulid', $request->validated('ulid'))->first();

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
