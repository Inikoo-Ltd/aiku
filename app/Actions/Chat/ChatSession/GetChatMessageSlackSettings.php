<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\ChatSession\Concerns\WithChatSlackSettings;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatMessageSlackSettings
{
    use AsAction;
    use WithChatSlackSettings;

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(string $organisation, ChatMessage $chatMessage): JsonResponse
    {
        $chatMessage->loadMissing('chatSession.shop');

        $slack = $this->shopSlackSettings($chatMessage->chatSession?->shop);

        return response()->json([
            'success' => true,
            'data'    => [
                'has_token'    => filled($slack['token']),
                'destinations' => $slack['destinations'],
            ],
        ]);
    }
}
