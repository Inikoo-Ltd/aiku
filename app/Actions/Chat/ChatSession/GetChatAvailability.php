<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\HumanResources\WorkSchedule\GetChatConfig;
use App\Models\Catalogue\Shop;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatAvailability
{
    use AsAction;

    public function handle(Shop $shop): bool
    {
        if (!$shop->website) {
            return false;
        }

        return GetChatConfig::run($shop->website)['is_online'];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json([
            'is_online' => $this->handle(Shop::findOrFail($request->validated('shop_id'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
        ];
    }
}
