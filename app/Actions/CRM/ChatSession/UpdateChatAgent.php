<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Enums\CRM\Livechat\ChatAgentSpecializationEnum;

class UpdateChatAgent
{
    use AsAction;

    public function rules(): array
    {
        return [
            'max_concurrent_chats' => 'sometimes|required|integer|min:1|max:100',
            'is_online'            => 'sometimes|required|boolean',
            'is_available'         => 'sometimes|required|boolean',
            'current_chat_count'   => 'sometimes|required|integer|min:0',
            'specialization'       => 'sometimes|nullable|array',
            'specialization.*'     => [
                'string',
                'max:50',
                Rule::in(ChatAgentSpecializationEnum::getValues())
            ],
            'auto_accept'          => 'sometimes|required|boolean',
            'language_id' => [
                'sometimes',
                'integer',
                'exists:languages,id',
            ],
        ];
    }
    // public function authorize(ActionRequest $request, ChatAgent $chatAgent): bool
    // {
    //     return true;
    // }

    public function asController(ActionRequest $request, $userId): ChatAgent
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

        $validated = $request->validated();

        return $this->handle($chatAgent, $validated);
    }



    public function handle(ChatAgent $chatAgent, array $data): ChatAgent
    {
        DB::beginTransaction();

        try {
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
        $chatAgent->load('user', 'language');

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
                'is_available' => $chatAgent->is_available,
                'specialization' => $chatAgent->specialization,
                'language' => [
                    'id' => $chatAgent->language?->id,
                    'name' => $chatAgent->language?->name,
                ],
                'updated_at' => $chatAgent->updated_at->toISOString(),
            ]
        ]);
    }
}
