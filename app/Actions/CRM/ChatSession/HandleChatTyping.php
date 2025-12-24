<?php

namespace App\Actions\CRM\ChatSession;

use App\Events\BroadcastTypingIndicator;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleChatTyping
{
    use AsAction;


    public function rule(): array
    {
        return [
            'is_typing' => 'required|boolean',
        ];
    }

    public function handle(ChatSession $chatSession, array $modelData): array
    {
        broadcast(new BroadcastTypingIndicator(
            $modelData['is_typing'],
            $chatSession->ulid,
        ))->toOthers();
    }

    public function asController(ChatSession $chatSession, ActionRequest $request)
    {
        $modelData = $request->validated();
        return $this->handle($chatSession, $modelData);
    }
}
