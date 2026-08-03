<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatAgent;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatAgentByUserId
{
    use AsAction;

    public function asController($userId): JsonResponse
    {
        $chatAgent = ChatAgent::where('user_id', $userId)->first();

        if (! $chatAgent) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Chat agent profile not found for this user.'
                ], 404)
            );
        }

        $shopDetails = $chatAgent->shopAssignments->map(function ($assignment) {
            $orgCode = $assignment->organisation->code ?? '-';
            $shopName = $assignment->shop->name ?? '-';
            return "{$orgCode} | {$shopName}";
        })->implode(', ');

        $user = $chatAgent->user;
        $name = $user->contact_name ?? $user->username ?? 'Unknown';


        return response()->json([
            'success' => true,
            'data' => [
                'id' => $chatAgent->id,
                'user' => [
                    'id'    => $user->id,
                    'name'  => $name,
                    'email' => $user->email,
                    'image' => $user->imageSources(48, 48),
                ],
                'max_concurrent_chats' => $chatAgent->max_concurrent_chats,
                'is_available'         => (bool) $chatAgent->is_available,
                'specialization'       => $chatAgent->specialization,

                'language' => $chatAgent->language ? [
                    'id'   => $chatAgent->language->id,
                    'name' => $chatAgent->language->name,
                ] : null,

                'shop_names' => $shopDetails,
                'is_online'  => (bool) $chatAgent->is_online,
                'current_chat_count' => $chatAgent->current_chat_count,
            ]
        ]);
    }
}
