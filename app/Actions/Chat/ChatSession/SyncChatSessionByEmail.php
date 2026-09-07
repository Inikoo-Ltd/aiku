<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncChatSessionByEmail
{
    use AsAction;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255', 'email'],
        ];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): array
    {
        $validated = $request->validated();
        return $this->handle($chatSession, $validated['email']);
    }

    public function handle(ChatSession $chatSession, string $email): array
    {
        $shopId = $chatSession->shop_id;

        // Only match a customer registered in the same shop the guest is chatting from,
        // never across the whole database.
        $webUser = $shopId
            ? WebUser::where('shop_id', $shopId)
                ->where(function ($query) use ($email) {
                    $query->where('email', $email)
                        ->orWhereHas('customer', function ($customerQuery) use ($email) {
                            $customerQuery->where('email', $email);
                        });
                })
                ->first()
            : null;

        if (!$webUser) {
            return [
                'success' => false,
                'message' => 'No customer registered with this email in this shop',
                'data' => [
                    'email' => $email,
                    'chat_session_ulid' => $chatSession->ulid,
                ],
                'status' => 404,
            ];
        }

        $chatSession->update([
            'web_user_id' => $webUser->id,
        ]);

        return [
            'success' => true,
            'message' => 'Chat session synced with web user by email',
            'data' => [
                'chat_session_ulid' => $chatSession->ulid,
                'web_user' => [
                    'id' => $webUser->id,
                    'name' => $webUser->contact_name,
                    'email' => $webUser->email ?? $webUser->customer->email ?? null,
                ],
            ],
            'status' => 200,
        ];
    }

    public function jsonResponse($result): JsonResponse
    {
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $result['data'] ?? [],
        ], $result['status'] ?? ($result['success'] ? 200 : 400));
    }
}
