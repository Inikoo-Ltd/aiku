<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\CloseMetaChatSession;
use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Like a ticket waiting on its reporter: the conversation stays open and in the agent's list
 * with the time it closes, anything the customer writes ends the wait, and if they write
 * nothing it closes then. An agent sets it, and an email that only thanks us gets it instead
 * of closing on the spot, so nobody loses sight of it. Days are working days, Monday to
 * Friday, so a wait never runs out over a weekend.
 */
class WaitForCustomerReply
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public int $jobTimeout = 60;
    public int $jobTries = 1;

    public const string KEY = 'waiting_for_customer';

    private const array CUSTOMER_SENDERS = [ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST];

    public function handle(ChatSession|MetaChatSession $chatSession, int $hours, string $reason = 'agent'): void
    {
        $lastMessageId = (int) $chatSession->messages()->max('id');
        $until         = now()->addWeekdays(intdiv($hours, 24))->addHours($hours % 24);

        SetChatSessionMetadata::run($chatSession, [self::KEY => ['message_id' => $lastMessageId, 'until' => $until->toISOString(), 'reason' => $reason]]);

        // ponytail: a delayed job per wait; a sweep over the metadata key if jobs days out ever get lost.
        static::dispatch($chatSession, $lastMessageId)->delay($until);
    }

    public function asJob(ChatSession|MetaChatSession $chatSession, int $lastMessageId): void
    {
        $chatSession->refresh();

        if (data_get($chatSession->metadata, self::KEY.'.message_id') !== $lastMessageId) {
            return;
        }

        self::stop($chatSession);

        if ($chatSession->status === ChatSessionStatusEnum::CLOSED || $this->customerWroteSince($chatSession, $lastMessageId)) {
            return;
        }

        $note = [
            'message_text' => 'Closed automatically: the customer did not write back while we waited. Anything they write next reopens it.',
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
            'is_read'      => true,
            'read_at'      => now(),
            'delivered_at' => now(),
            'metadata'     => ['automated' => ChatAutomationKindEnum::WAITED_CLOSED->value],
        ];

        if ($chatSession instanceof MetaChatSession) {
            CloseMetaChatSession::run($chatSession, null, ChatActorTypeEnum::SYSTEM, ['reason' => 'no_customer_reply']);
            StoreMetaChatMessage::run($chatSession, $note);

            return;
        }

        CloseChatSession::run($chatSession, null, ChatActorTypeEnum::SYSTEM, ['reason' => 'no_customer_reply']);
        $chatSession->messages()->create($note);
    }

    public static function until(ChatSession|MetaChatSession $chatSession): ?string
    {
        return $chatSession->status === ChatSessionStatusEnum::CLOSED ? null : data_get($chatSession->metadata, self::KEY.'.until');
    }

    public static function stop(ChatSession|MetaChatSession $chatSession): void
    {
        if (!array_key_exists(self::KEY, $chatSession->metadata ?? [])) {
            return;
        }

        SetChatSessionMetadata::run($chatSession, [self::KEY => null]);
    }

    private function customerWroteSince(ChatSession|MetaChatSession $chatSession, int $lastMessageId): bool
    {
        return $chatSession->messages()
            ->where('id', '>', $lastMessageId)
            ->whereIn('sender_type', array_map(fn (ChatSenderTypeEnum $sender) => $sender->value, self::CUSTOMER_SENDERS))
            ->exists();
    }

    public function rules(): array
    {
        return [
            'hours' => ['present', 'nullable', 'integer', 'min:1', 'max:720'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return $this->respond($chatSession, $request->validated('hours'));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWhatsapp(?string $organisation, MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        return $this->respond($metaChatSession, $request->validated('hours'));
    }

    private function respond(ChatSession|MetaChatSession $chatSession, ?int $hours): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession) || $chatSession->status === ChatSessionStatusEnum::CLOSED) {
            return response()->json(['success' => false], 403);
        }

        if ($hours === null) {
            self::stop($chatSession);
        } else {
            $this->handle($chatSession, $hours);
        }

        return response()->json(['success' => true, 'waiting_until' => self::until($chatSession->refresh())]);
    }
}
