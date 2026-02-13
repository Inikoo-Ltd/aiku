<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\HumanResources\WorkSchedule\GetChatConfig;
use App\Models\Catalogue\Shop;
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
                'is_online'   => false,
                'schedule'    => null,
                'session'     => $chatSession,
                'is_user'     => $isUser,
                'is_metadata' => $isMetadata,
            ];
        }

        $chatConfig = GetChatConfig::run($website);

        return [
            'is_online'   => $chatConfig['is_online'],
            'schedule'    => $chatConfig['schedule'],
            'session'     => $chatSession,
            'is_user'     => $isUser,
            'is_metadata' => $isMetadata,
        ];
    }


    public function asController(ActionRequest $request): JsonResponse
    {

        $shop = Shop::findOrFail($request->validated('shop_id'));
        $chatSession = ChatSession::findOrFail($request->validated('ulid'));

        $config = $this->handle($shop, $chatSession);

        return response()->json([
            'chat_config' => $config
        ]);
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'ulid' => ['required', 'ulid', 'exists:chat_sessions,ulid'],
        ];
    }
}
