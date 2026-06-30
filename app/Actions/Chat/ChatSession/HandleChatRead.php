<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleChatRead
{
    use AsAction;

    public function rules(): array
    {
        return [
            'session_ulid' => ['required', 'string', 'exists:chat_sessions,ulid'],
            'request_from' => [
                'required',
                'string',
                Rule::in([
                    ChatSenderTypeEnum::AGENT->value,
                    ChatSenderTypeEnum::USER->value,
                    ChatSenderTypeEnum::GUEST->value,
                ]),
            ],
        ];
    }

    public function handle(ActionRequest $request): array
    {
        $validated = $request->validated();
        $chatSession = ChatSession::where('ulid', $validated['session_ulid'])->firstOrFail();
        $enum = ChatSenderTypeEnum::tryFrom($validated['request_from']);
        if ($enum) {
            MarkChatMessagesAsRead::run($chatSession, $enum);
            return [
                'success' => true,
                'message' => 'Messages marked as read successfully'
            ];
        }
        return [
            'success' => false,
            'message' => 'Invalid reader type'
        ];
    }

    public function read(ActionRequest $request)
    {
        $validated = $request->validated();

        $chatSession = ChatSession::where('ulid', $validated['session_ulid'])->firstOrFail();

        $readerType = ChatSenderTypeEnum::tryFrom($validated['request_from']) ?? ChatSenderTypeEnum::GUEST;

        $this->handle($chatSession, $readerType->value);
    }

    public function jsonResponse(array $data): jsonResponse
    {
        return response()->json($data);
    }
}
