<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatAgent;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreChatAgent
{
    use AsAction;

    public function handle(array $modelData): ChatAgent
    {
        $user = User::findOrFail($modelData['user_id']);

        $existingAgent = ChatAgent::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if ($existingAgent) {
            throw new Exception('User already has an active chat agent profile.');
        }

        $chatAgent = ChatAgent::create([
            'user_id' => $modelData['user_id'],
            'max_concurrent_chats' => $modelData['max_concurrent_chats'] ?? 10,
            'is_online' => $modelData['is_online'] ?? false,
            'is_available' => $modelData['is_available'] ?? true,
            'current_chat_count' => 0,
            'specialization' => $modelData['specialization'] ?? [],
            'auto_accept' => $modelData['auto_accept'] ?? false,
        ]);

        return $chatAgent;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $existing = ChatAgent::where('user_id', $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($existing) {
                        $fail('The selected user already has an active chat agent profile.');
                    }
                }
            ],
            'max_concurrent_chats' => [
                'nullable',
                'integer',
                'min:1',
                'max:50'
            ],
            'is_online' => [
                'nullable',
                'boolean'
            ],
            'is_available' => [
                'nullable',
                'boolean'
            ],
            'specialization' => [
                'nullable',
                'array'
            ],
            'specialization.*' => [
                'string',
                'max:50'
            ],
            'auto_accept' => [
                'nullable',
                'boolean'
            ],
        ];
    }

    public function asController(Request $request): JsonResponse
    {
        if (!$this->authorizeCreateAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to create chat agent profiles.'
            ], 403);
        }

        try {
            $chatAgent = $this->handle($request->validated());

            return $this->jsonResponse($chatAgent);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }


    public function jsonResponse(ChatAgent $chatAgent): JsonResponse
    {
        $chatAgent->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Chat agent profile created successfully',
            'data' => [
                'id' => $chatAgent->id,
                'user' => [
                    'id' => $chatAgent->user->id,
                    'name' => $chatAgent->user->contact_name,
                    'email' => $chatAgent->user->email,
                    'username' => $chatAgent->user->username,
                ],
                'max_concurrent_chats' => $chatAgent->max_concurrent_chats,
                'is_online' => $chatAgent->is_online,
                'is_available' => $chatAgent->is_available,
                'current_chat_count' => $chatAgent->current_chat_count,
                'specialization' => $chatAgent->specialization ?? [],
                'auto_accept' => $chatAgent->auto_accept,
                'available_slots' => $chatAgent->getAvailableSlots(),
                 ]
        ]);
    }


    public function htmlResponse(ChatAgent $chatAgent): JsonResponse
    {
        return $this->jsonResponse($chatAgent);
    }
}