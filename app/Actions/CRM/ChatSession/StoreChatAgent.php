<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use App\Models\SysAdmin\User;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreChatAgent
{
    use AsAction;

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',

                function ($attribute, $value, $fail) {
                    $exists = ChatAgent::where('user_id', $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        $fail('User already has an active chat agent profile.');
                    }
                }
            ],

            'max_concurrent_chats' => 'nullable|integer|min:1|max:50',
            'is_online'            => 'nullable|boolean',
            'is_available'         => 'nullable|boolean',
            'specialization'       => 'nullable|array',
            'specialization.*'     => 'string|max:50',
            'auto_accept'          => 'nullable|boolean',
        ];
    }


    public function asController(ActionRequest $request): ChatAgent
    {
        $validated = $request->validated();

        if (!$this->authorizeCreateAgent()) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to create chat agent profiles.'
                ], 403)
            );
        }

        return $this->handle($validated);
    }


    public function handle(array $data): ChatAgent
    {
        DB::beginTransaction();

        try {
            $user = User::find($data['user_id']);
            if (!$user) {
                 throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404));
            }

              $chatAgentData = [
                'user_id'              => $data['user_id'],
                'max_concurrent_chats' => $data['max_concurrent_chats'] ?? 10,
                'is_online'            => $data['is_online'] ?? false,
                'is_available'         => $data['is_available'] ?? true,
                'current_chat_count'   => 0,
                'specialization'       => $data['specialization'] ?? [],
                'auto_accept'          => $data['auto_accept'] ?? false,
            ];

            $chatAgent = ChatAgent::create($chatAgentData);
            DB::commit();

            return $chatAgent;

        } catch (Exception $e) {
            DB::rollBack();

            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Failed to create chat agent profile: ' . $e->getMessage()
                ], 500)
            );
        }
    }



    public function jsonResponse(ChatAgent $chatAgent): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat agent profile created successfully',
            'data'    => $chatAgent,
        ]);
    }

    protected function authorizeCreateAgent(): bool
    {
        return true;
    }
}