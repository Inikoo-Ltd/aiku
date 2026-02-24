<?php

namespace App\Actions\CRM\ChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\Livechat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Catalogue\Shop;

class StoreOfflineMessage
{
    use AsAction;

    public function handle(Shop $shop, array $data): ChatSession
    {
        return DB::transaction(function () use ($shop, $data) {
            $session = $this->findSession($shop, $data['session_ulid'] ?? null);

            if ($session) {
                $this->reopenSessionIfNeeded($session, $data);
            } else {
                $session = $this->createSession($shop, $data);
            }

            $this->updateOfflineContactMetadata($session, $data);

            $this->sendOfflineMessage($session, $data);

            return $session;
        });
    }

    private function findSession(Shop $shop, ?string $ulid): ?ChatSession
    {
        if (empty($ulid)) {
            return null;
        }

        return ChatSession::where('ulid', $ulid)
            ->where('shop_id', $shop->id)
            ->first();
    }

    private function reopenSessionIfNeeded(ChatSession $session, array $data): void
    {
        if (! $session->isClosed()) {
            return;
        }

        $session->update([
            'status' => ChatSessionStatusEnum::ACTIVE,
        ]);

        $session->assignments()->update([
            'status' => ChatAssignmentStatusEnum::ACTIVE,
        ]);

        $this->logReopenEvent($session, $data);
    }

    private function logReopenEvent(ChatSession $session, array $data): void
    {
        $payload = [
            'reopened_at' => now()->toISOString(),
            'session_previous_status' => $session->getOriginal('status'),
            'session_new_status' => $session->status->value,
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
            'is_offline_message' => true,
        ];

        $actorType = $data['sender_type'] ? ChatActorTypeEnum::USER : ChatActorTypeEnum::GUEST;

        StoreChatEvent::make()->customEvent(
            $session,
            ChatEventTypeEnum::REOPEN,
            $actorType,
            $data['web_user_id'],
            $payload
        );
    }

    private function createSession(Shop $shop, array $data): ChatSession
    {
        return StoreChatSession::run([
            'shop_id' => $shop->id ?? null,
            'web_user_id' => $data['web_user_id'] ?? null,
            'language_id' => $data['language_id'] ?? null,
            'priority' => ChatPriorityEnum::NORMAL,
        ]);
    }

    private function updateOfflineContactMetadata(ChatSession $session, array $data): void
    {
        $old = $session->metadata ?? [];

        $session->update([
            'metadata' => array_merge($old, [
                'name'  => $data['name']  ?? $old['name']  ?? null,
                'email' => $data['email'] ?? $old['email'] ?? null,
            ]),
        ]);
    }

    private function sendOfflineMessage(ChatSession $session, array $data): void
    {
        $messageData = [
            'message_text' => $data['message'],
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type' => $data['sender_type'] === ChatSenderTypeEnum::USER->value
                ? ChatSenderTypeEnum::USER->value
                : ChatSenderTypeEnum::GUEST->value,
            'sender_id' => $data['web_user_id'] ?? null,
        ];

        $message = SendChatMessage::run($session, $messageData);

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                'is_offline_message' => true,
            ]),
        ]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $shop = Shop::findOrFail($request->validated('shop_id'));

        $session = $this->handle($shop, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => __('Message received. We will contact you via email.'),
            'session_ulid' => $session->ulid,
            'data' => $session,
        ]);
    }

    public function rules(ActionRequest $request): array
    {
        return [
            'web_user_id' => [
                Rule::requiredIf(fn () => $request->input('sender_type') === ChatSenderTypeEnum::USER->value),
                'nullable',
                'exists:web_users,id',
            ],

            'shop_id' => ['required', 'exists:shops,id'],
            'session_ulid' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'language_id' => ['required', 'exists:languages,id'],
            'sender_type' => [
                'required',
                Rule::enum(ChatSenderTypeEnum::class),
            ],
        ];
    }
}
