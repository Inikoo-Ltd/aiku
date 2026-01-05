<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Events\BroadcastTypingIndicator;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleChatTyping
{
    use AsAction;


    public function rules(): array
    {
        return [
            'session_ulid' => 'required|string|exists:chat_sessions,ulid',
            'user_name'   => 'required|string',
            'is_typing'   => 'required|boolean'
        ];
    }

    public function handle(array $modelData): array
    {
        broadcast(new BroadcastTypingIndicator(
            $modelData['user_name'],
            $modelData['is_typing'],
            $modelData['session_ulid'],
        ))->toOthers();

        return array_merge($modelData, [
            'event_type' => 'typing_indicator',
        ]);
    }

    public function asController(ActionRequest $request)
    {
        $modelData = $request->validated();
        return $this->handle($modelData);
    }

    public function jsonResponse(array $modelData): JsonResponse
    {
        return response()->json($modelData);
    }
}
