<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetActiveChatSessions
{
    use AsAction;

    public function handle(Organisation $organisation): array
    {
        $shopIds = $organisation->shops()->pluck('id');

        return ChatSession::query()
            ->whereIn('shop_id', $shopIds)
            ->whereIn('status', [
                ChatSessionStatusEnum::ACTIVE->value,
                ChatSessionStatusEnum::WAITING->value,
            ])
            ->withCount('messages')
            ->select(['id', 'status', 'geo_country_code','created_at'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ChatSession $session) => [
                'id'           => $session->id,
                'status'       => $session->status->value,
                'has_messages' => $session->messages_count > 0,
                'country_code' => $session->geo_country_code ?? 'XX',
            ])
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation): array
    {
        return $this->handle($organisation);
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return response()->json($data);
    }
}
