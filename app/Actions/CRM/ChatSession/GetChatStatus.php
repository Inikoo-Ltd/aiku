<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\HumanResources\WorkSchedule\GetChatConfig;
use App\Models\Catalogue\Shop;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatStatus
{
    use AsAction;

    public function handle(Shop $shop): array
    {

        $website = $shop->website;

        if (!$website) {
            return [
                'is_online' => false,
                'schedule'  => null,
            ];
        }

        return GetChatConfig::run($website);
    }

    public function asController(ActionRequest $request): JsonResponse
    {

        $shop = Shop::findOrFail($request->validated('shop_id'));

        $config = $this->handle($shop);

        return response()->json([
            'chat_config' => $config
        ]);
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
        ];
    }
}
