<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateChatAgent
{
    use AsAction;

     public function rules(): array
    {
        return [
            'user_id' => [
                'sometimes',
                'required',
                'exists:users,id',
            ],
            'max_concurrent_chats' => 'sometimes|required|integer|min:1|max:50',
            'is_online'            => 'sometimes|required|boolean',
            'is_available'         => 'sometimes|required|boolean',
            'current_chat_count'   => 'sometimes|required|integer|min:0',
            'specialization'       => 'sometimes|nullable|array',
            'specialization.*'     => 'string|max:50',
            'auto_accept'          => 'sometimes|required|boolean',
        ];
    }





    public function authorize(ActionRequest $request, ChatAgent $chatAgent): bool
    {
        return true;
    }


    public function asController(ActionRequest $request, ChatAgent $chatAgent): ChatAgent
    {
        $validated = $request->validated();

        return $this->handle($chatAgent, $validated);
    }



    public function handle(ChatAgent $chatAgent, array $data): ChatAgent
    {

        DB::beginTransaction();

        try {

            if (isset($data['user_id']) && $data['user_id'] !== $chatAgent->user_id) {
                $exists = ChatAgent::where('user_id', $data['user_id'])
                    ->where('id', '!=', $chatAgent->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    throw new HttpResponseException(
                        response()->json([
                            'success' => false,
                            'message' => 'User already has an active chat agent profile.'
                        ], 422)
                    );
                }
            }

            if (isset($data['is_available']) && !$data['is_available']) {
                $data['current_chat_count'] = 0;
            }

            if (
                isset($data['max_concurrent_chats'])
                && $data['max_concurrent_chats'] < $chatAgent->current_chat_count
            ) {
                $data['current_chat_count'] = $data['max_concurrent_chats'];
            }

            $chatAgent->update($data);

            DB::commit();

            return $chatAgent->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Failed to update chat agent profile: ' . $e->getMessage()
                ], 500)
            );
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
                'specialization' => $chatAgent->specialization,
                'auto_accept' => $chatAgent->auto_accept,
                'updated_at' => $chatAgent->updated_at->toISOString(),
            ]
        ]);
    }
}
