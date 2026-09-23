<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use App\Notifications\ForwardedChatSessionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ForwardChatSessionToColleague
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const TRANSCRIPT_MESSAGES = 20;

    /**
     * @param array{user_ids: array<int, int>, note?: string|null, also_email?: bool} $modelData
     */
    public function handle(ChatSession $chatSession, ChatAgent $agent, array $modelData): StaffConversation
    {
        $recipients = User::whereIn('id', $modelData['user_ids'])
            ->where('group_id', $agent->user->group_id)
            ->where('status', true)
            ->get();

        $note = trim((string) Arr::get($modelData, 'note', ''));

        $conversation = DB::transaction(function () use ($chatSession, $agent, $recipients, $note) {
            // One staff thread per forwarded conversation, not one per forward: the second person
            // brought in reads what was already said instead of starting an empty thread beside it.
            $key = 'ctx:ChatSession:'.$chatSession->id;

            $conversation = StaffConversation::where('dm_key', $key)->first() ?? StaffConversation::create([
                'group_id'           => $agent->user->group_id,
                'type'               => 'group',
                'name'               => $this->title($chatSession),
                'dm_key'             => $key,
                'context_type'       => 'ChatSession',
                'context_id'         => $chatSession->id,
                'created_by_user_id' => $agent->user_id,
            ]);

            $conversation->participants()->syncWithoutDetaching(
                $recipients->pluck('id')->push($agent->user_id)->unique()->all()
            );

            SendStaffMessage::make()->handle($conversation, $agent->user, [
                'body' => $this->staffMessageBody($chatSession, $note),
            ]);

            return $conversation;
        });

        // The colleague reads and answers this inside Aiku; the mail is a nudge for whoever does
        // not live in the inbox, so it carries the conversation with it and still points back here.
        if (Arr::get($modelData, 'also_email')) {
            $emailable = $recipients->filter(fn (User $user) => filled($user->email));

            if ($emailable->isNotEmpty()) {
                Notification::send($emailable, new ForwardedChatSessionNotification(
                    title: $this->title($chatSession),
                    forwardedBy: $agent->user->contact_name ?? $agent->user->username,
                    note: $note,
                    transcript: $this->transcript($chatSession),
                    url: $this->sessionUrl($chatSession),
                    replyTo: $agent->user->email,
                ));
            }
        }

        StoreChatEvent::make()->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::FORWARD,
            actorType: ChatActorTypeEnum::AGENT,
            actorId: $agent->id,
            payload: [
                'forwarded_by_agent_id'   => $agent->id,
                'forwarded_by_agent_name' => $agent->user?->contact_name,
                'recipient_names'         => $recipients->map(fn (User $user) => $user->contact_name ?? $user->username)->values()->all(),
                'recipient_user_ids'      => $recipients->pluck('id')->values()->all(),
                'also_emailed'            => (bool) Arr::get($modelData, 'also_email'),
                'staff_conversation_id'   => $conversation->id,
                'note'                    => $note ?: null,
            ]
        );

        return $conversation;
    }

    private function title(ChatSession $chatSession): string
    {
        return Arr::get($chatSession->metadata ?? [], 'email_subject')
            ?: __('Conversation :ulid', ['ulid' => $chatSession->ulid]);
    }

    private function sessionUrl(ChatSession $chatSession): ?string
    {
        $orgSlug = $chatSession->shop?->organisation?->slug;

        return $orgSlug
            ? route('grp.org.chat.conversations.detail', ['organisation' => $orgSlug, 'chatSession' => $chatSession->id])
            : null;
    }

    private function staffMessageBody(ChatSession $chatSession, string $note): string
    {
        $lines = [$note ?: __('Forwarding this to you.')];

        $contact = $chatSession->webUser?->customer?->contact_name
            ?? $chatSession->webUser?->username
            ?? $chatSession->guest_identifier;

        $lines[] = '';
        $lines[] = __('Subject: :subject', ['subject' => $this->title($chatSession)]);

        if ($contact) {
            $lines[] = __('From: :contact', ['contact' => $contact]);
        }

        if ($url = $this->sessionUrl($chatSession)) {
            $lines[] = $url;
        }

        return implode("\n", $lines);
    }

    private function transcript(ChatSession $chatSession): string
    {
        return $chatSession->messages()
            ->whereNotNull('message_text')
            ->latest('created_at')
            ->limit(self::TRANSCRIPT_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn ($message) => sprintf(
                '[%s] %s: %s',
                $message->created_at?->format('d M Y H:i'),
                ChatSenderTypeEnum::labels()[$message->sender_type->value] ?? $message->sender_type->value,
                trim((string) $message->message_text)
            ))
            ->join("\n\n");
    }

    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1', 'max:10'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'note'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'also_email' => ['sometimes', 'boolean'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only agents of this shop can forward a conversation')], 403);
        }

        $conversation = $this->handle($chatSession, $agent, $request->validate($this->rules()));

        return response()->json([
            'success' => true,
            'message' => __('Forwarded'),
            'data'    => ['staff_conversation_id' => $conversation->id],
        ]);
    }
}
