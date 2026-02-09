<?php

namespace App\Actions\CRM\ChatSession;

use App\Models\Catalogue\Shop;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\CRM\Livechat\ChatSession;

class StoreOfflineMessage
{
    use AsAction;

    public function handle(Shop $shop, array $data): ChatSession
    {

        return DB::transaction(
            function () use ($shop, $data) {
                $session = null;

                if (!empty($data['session_ulid'])) {
                    $session = ChatSession::where('ulid', $data['session_ulid'])
                    ->where('shop_id', $shop->id)
                    ->first();
                }

                if ($session && $session->isClosed()) {
                    $session->update([
                        'status' => ChatSessionStatusEnum::ACTIVE,
                    ]);
                    $session->assignments()->update([
                        'status' => ChatAssignmentStatusEnum::ACTIVE,
                    ]);
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
                    //Even Log
                    StoreChatEvent::make()->customEvent($session, ChatEventTypeEnum::REOPEN, $actorType, $data['sender_id'], $payload);
                }

                if (!$session) {

                    $sessionData = [
                        'shop_id'     => $shop->id ?? null,
                        'web_user_id' => $data['web_user_id'] ?? null,
                        'language_id' => $data['language_id'] ?? null,
                        'priority'    => ChatPriorityEnum::NORMAL,
                    ];

                    $session = StoreChatSession::run($sessionData);
                }


                $currentMetadata = $session->metadata ?? [];
                $currentMetadata['offline_contact'] = [
                    'name'  => $data['name'],
                    'email' => $data['email'],
                ];

                $session->update([
                    'metadata'    => $currentMetadata
                ]);

                // 3. Send Message via SendChatMessage Action
                $messageData = [
                    'message_text' => $data['message'],
                    'message_type' => ChatMessageTypeEnum::TEXT->value,
                    'sender_type'  => $data['sender_type'] === ChatSenderTypeEnum::USER->value ? ChatSenderTypeEnum::USER->value : ChatSenderTypeEnum::GUEST->value,
                    'sender_id'    => $data['sender_id'] ?? null,
                ];


                $message = SendChatMessage::run($session, $messageData);

                // 4. Update Message Metadata specifically for Offline
                $message->update([
                    'metadata' => array_merge($message->metadata ?? [], [
                        'is_offline_message' => true
                    ])
                ]);

                return $session;
            }
        );
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $shop = Shop::findOrFail($request->validated('shop_id'));

        $session = $this->handle($shop, $request->validated());

        return response()->json([
            'status'       => 'success',
            'message'      => __('Message received. We will contact you via email.'),
            'session_ulid' => $session->ulid,
            'data'         => $session
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

            'shop_id'      => ['required', 'exists:shops,id'],
            'session_ulid' => ['nullable', 'string'],
            'name'         => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:150'],
            'message'      => ['required', 'string', 'max:5000'],
            'language_id'  => ['required', 'exists:languages,id'],

            'sender_type' => [
                'required',
                Rule::enum(ChatSenderTypeEnum::class),
            ],
        ];
    }
}
