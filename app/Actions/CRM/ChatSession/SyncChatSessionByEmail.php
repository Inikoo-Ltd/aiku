<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\WebUser;
use App\Models\CRM\Livechat\ChatSession;

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
        $webUser = WebUser::where('email', $email)->first();

        if (!$webUser) {
            $webUser = WebUser::whereHas('customer', function ($q) use ($email) {
                $q->where('email', $email);
            })->first();
        }

        if (!$webUser) {
            return [
                'success' => false,
                'message' => 'No customer found for the provided email',
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
