<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Events\BroadcastTypingIndicator;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
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
