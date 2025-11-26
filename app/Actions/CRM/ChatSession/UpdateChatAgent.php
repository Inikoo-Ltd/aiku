<?php

namespace App\Actions\CRM\ChatSession;

use Lorisleiva\Actions\Concerns\AsAction;

class UpdateChatAgent
{
    use AsAction;

    public function handle(ChatAgent $chatAgent, array $modelData): ChatAgent
    {
        if (isset($modelData['user_id']) && $modelData['user_id'] !== $chatAgent->user_id) {
            $this->validateUniqueUser($modelData['user_id'], $chatAgent->id);
        }

        if (isset($modelData['is_available']) && !$modelData['is_available']) {
            $modelData['current_chat_count'] = 0;
        }

        if (isset($modelData['max_concurrent_chats']) && $modelData['max_concurrent_chats'] < $chatAgent->current_chat_count) {
            $modelData['current_chat_count'] = $modelData['max_concurrent_chats'];
        }

        $chatAgent->update($modelData);

        return $chatAgent->fresh();
    }

    private function validateUniqueUser(int $userId, int $excludeAgentId): void
    {
        $existingAgent = ChatAgent::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('id', '!=', $excludeAgentId)
            ->first();

        if ($existingAgent) {
            throw new Exception('The selected user already has an active chat agent profile.');
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'sometimes',
                'required',
                'exists:users,id',
            ],
            'max_concurrent_chats' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:50'
            ],
            'is_online' => [
                'sometimes',
                'required',
                'boolean'
            ],
            'is_available' => [
                'sometimes',
                'required',
                'boolean'
            ],
            'current_chat_count' => [
                'sometimes',
                'required',
                'integer',
                'min:0'
            ],
            'specialization' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'specialization.*' => [
                'string',
                'max:50'
            ],
            'auto_accept' => [
                'sometimes',
                'required',
                'boolean'
            ],
        ];
    }

    public function getValidationAttributes(): array
    {
        return [
            'user_id' => 'user',
            'max_concurrent_chats' => 'max concurrent chats',
            'is_online' => 'online status',
            'is_available' => 'availability status',
            'current_chat_count' => 'current chat count',
            'specialization' => 'specialization',
            'auto_accept' => 'auto accept',
        ];
    }

    public function authorize(Request $request, ChatAgent $chatAgent): bool
    {
        return $request->user()->can('update', $chatAgent) ||
               $request->user()->hasRole('admin') ||
               $request->user()->id === $chatAgent->user_id;
    }

    public function asController(Request $request, ChatAgent $chatAgent): JsonResponse
    {
        if (!$this->authorize($request, $chatAgent)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this chat agent profile.'
            ], 403);
        }

        try {
            $validatedData = $this->validate($request, $this->rules());
            $updatedAgent = $this->handle($chatAgent, $validatedData);

            return $this->jsonResponse($updatedAgent);

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
            'message' => 'Chat agent profile updated successfully',
            'data' => [
                'id' => $chatAgent->id,
                'user' => [
                    'id' => $chatAgent->user->id,
                    'name' => $chatAgent->user->contact_name,
                    'email' => $chatAgent->user->email,
                ],
                'max_concurrent_chats' => $chatAgent->max_concurrent_chats,
                'is_online' => $chatAgent->is_online,
                'is_available' => $chatAgent->is_available,
                'current_chat_count' => $chatAgent->current_chat_count,
                'specialization' => $chatAgent->specialization ?? [],
                'auto_accept' => $chatAgent->auto_accept,
                'available_slots' => $chatAgent->getAvailableSlots(),
                'updated_at' => $chatAgent->updated_at->toISOString(),
            ]
        ]);
    }


    public function handleStatusUpdate(ChatAgent $chatAgent, string $status, bool $value): ChatAgent
    {
        $allowedStatuses = ['is_online', 'is_available', 'auto_accept'];

        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Invalid status type.');
        }

        $updateData = [$status => $value];

        if ($status === 'is_available' && !$value) {
            $updateData['current_chat_count'] = 0;
        }

        return $this->handle($chatAgent, $updateData);
    }



    public function handleSpecializationUpdate(ChatAgent $chatAgent, array $specializations): ChatAgent
    {
        $validated = validator($specializations, [
            'specializations' => 'required|array',
            'specializations.*' => 'string|max:50'
        ])->validate();

        return $this->handle($chatAgent, ['specialization' => $validated['specializations']]);
    }

     public function asUpdateSpecialization(Request $request, ChatAgent $chatAgent): JsonResponse
    {
        try {
            $updatedAgent = $this->handleSpecializationUpdate($chatAgent, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Specialization updated successfully',
                'data' => [
                    'id' => $updatedAgent->id,
                    'specialization' => $updatedAgent->specialization,
                    'updated_at' => $updatedAgent->updated_at->toISOString(),
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }



    public function asToggleOnline(Request $request, ChatAgent $chatAgent): JsonResponse
    {
        try {
            $updatedAgent = $this->handleStatusUpdate($chatAgent, 'is_online', !$chatAgent->is_online);

            return response()->json([
                'success' => true,
                'message' => 'Online status updated successfully',
                'data' => [
                    'is_online' => $updatedAgent->is_online,
                    'is_available' => $updatedAgent->is_available,
                    'current_chat_count' => $updatedAgent->current_chat_count,
                    'available_slots' => $updatedAgent->getAvailableSlots(),
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }


    public function asToggleAvailable(Request $request, ChatAgent $chatAgent): JsonResponse
    {
        try {

             $updatedAgent = $this->handleStatusUpdate($chatAgent, 'is_online', !$chatAgent->is_online);

            return response()->json([
                'success' => true,
                'message' => 'Availability status updated successfully',
                'data' => [
                    'is_online' => $updatedAgent->is_online,
                    'is_available' => $updatedAgent->is_available,
                    'current_chat_count' => $updatedAgent->current_chat_count,
                    'available_slots' => $updatedAgent->getAvailableSlots(),
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}