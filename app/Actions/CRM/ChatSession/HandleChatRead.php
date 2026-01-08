<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;

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
