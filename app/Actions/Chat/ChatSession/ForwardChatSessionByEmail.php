<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sends the whole conversation, as text, to any address: a supplier, a courier, a colleague
 * outside Aiku. It goes out as a new email conversation from the shop's mailbox, so an answer
 * to it comes back to the inbox. Files are not carried, only named: they live in our storage
 * or still in Gmail, and fetching every one of them to attach again is not worth a forward.
 */
class ForwardChatSessionByEmail
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const string SEPARATOR = '---------- Forwarded message ----------';

    /**
     * @param  array{email: string, note?: string|null}  $modelData
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, User $user, array $modelData): ChatSession
    {
        $note    = trim((string) Arr::get($modelData, 'note', ''));
        $subject = self::subject($chatSession);

        $forwarded = StartCustomerEmailChat::make()->action($chatSession->shop, [
            'email'   => $modelData['email'],
            'subject' => $subject,
            'message' => self::message($chatSession, $note),
        ]);

        $agent = $this->chatAgentProfileFor($user);

        StoreChatEvent::make()->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::FORWARDED_BY_EMAIL,
            actorType: ChatActorTypeEnum::AGENT,
            actorId: $agent->id,
            payload: [
                'forwarded_by_agent_id'   => $agent->id,
                'forwarded_by_agent_name' => $user->contact_name,
                'recipients'              => StartCustomerEmailChat::splitAddresses($modelData['email']),
                'subject'                 => $subject,
                'forwarded_session_id'    => $forwarded->id,
                'forwarded_session_ulid'  => $forwarded->ulid,
                'note'                    => $note ?: null,
            ]
        );

        return $forwarded;
    }

    public static function subject(ChatSession $chatSession): string
    {
        $subject = trim((string) Arr::get($chatSession->metadata ?? [], 'email_subject'))
            ?: __('Conversation :ulid', ['ulid' => $chatSession->ulid]);

        return 'Fwd: '.$subject;
    }

    public static function message(ChatSession $chatSession, string $note): string
    {
        $forwarded = self::forwardedBlock($chatSession);

        return $note === '' ? $forwarded : $note."\n\n".$forwarded;
    }

    public static function forwardedBlock(ChatSession $chatSession): string
    {
        $messages = $chatSession->messages()
            ->with('media')
            ->where('sender_type', '!=', ChatSenderTypeEnum::SYSTEM->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $agents   = ChatAgent::with('user')
            ->whereIn('id', $messages->where('sender_type', ChatSenderTypeEnum::AGENT)->pluck('sender_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $timezone = $chatSession->shop?->timezoneName() ?? config('app.timezone');

        $entries = $messages
            ->map(fn (ChatMessage $message) => self::entry($chatSession, $message, $agents, $timezone))
            ->filter()
            ->all();

        return implode("\n\n", [
            self::SEPARATOR,
            __('Subject: :subject', ['subject' => Arr::get($chatSession->metadata ?? [], 'email_subject') ?: '-']),
            ...$entries,
        ]);
    }

    /**
     * @param  Collection<int, ChatAgent>  $agents
     */
    private static function entry(ChatSession $chatSession, ChatMessage $message, Collection $agents, string $timezone): ?string
    {
        $text  = trim((string) ($message->original_text ?? $message->message_text));
        $files = $message->attachedFiles()->map(fn ($file) => $file->name ?: $file->file_name)->filter()->values();

        if ($text === '' && $files->isEmpty()) {
            return null;
        }

        $lines = [
            __('From: :sender', ['sender' => self::senderLabel($chatSession, $message, $agents)]),
            __('Date: :date', ['date' => $message->created_at?->copy()->timezone($timezone)->format('D, j M Y H:i T')]),
        ];

        if ($text !== '') {
            $lines[] = '';
            $lines[] = $text;
        }

        if ($files->isNotEmpty()) {
            $lines[] = '';
            $lines[] = __('Attachments not forwarded: :files', ['files' => $files->join(', ')]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, ChatAgent>  $agents
     */
    private static function senderLabel(ChatSession $chatSession, ChatMessage $message, Collection $agents): string
    {
        $shopName = $chatSession->shop?->name ?? '';

        if ($message->sender_type === ChatSenderTypeEnum::AGENT) {
            $name = $agents->get($message->sender_id)?->user?->contact_name;

            return $name ? trim("{$name}, {$shopName}", ', ') : $shopName;
        }

        if (in_array($message->sender_type, [ChatSenderTypeEnum::AI, ChatSenderTypeEnum::SYSTEM_CAMPAIGN], true)) {
            return $shopName;
        }

        $from    = Arr::get($message->metadata ?? [], 'email_from', []);
        $address = Arr::get($from, 'address') ?: Arr::get($chatSession->metadata ?? [], 'email');
        $name    = Arr::get($from, 'name')
            ?: Arr::get($chatSession->metadata ?? [], 'name')
            ?: $chatSession->webUser?->customer?->contact_name;

        // Not "Name <address>": the message is stored through SendChatMessage, whose strip_tags
        // takes anything in angle brackets for markup and drops the address.
        if ($name && $address && $name !== $address) {
            return "{$name} ({$address})";
        }

        return $name ?: $address ?: __('Customer');
    }

    public function userMayForward(mixed $user, ChatSession $chatSession): bool
    {
        $shop = $chatSession->shop;

        return $user instanceof User
            && $shop instanceof Shop
            && ($this->userCanActOnChatOnShop($user, $shop) || StartCustomerEmailChat::userCanEmailCustomersOnShop($user, $shop));
    }

    public function authorize(ActionRequest $request): bool
    {
        $chatSession = $request->route('chatSession');

        return $chatSession instanceof ChatSession && $this->userMayForward($request->user(), $chatSession);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (StartCustomerEmailChat::splitAddresses((string) $value) as $address) {
                        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('":address" is not a valid email address.', ['address' => $address]));
                        }
                    }
                },
            ],
            'note'  => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     *
     * @throws \Throwable
     */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $forwarded = $this->handle($chatSession, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Forwarded'),
            'data'    => ['forwarded_session_ulid' => $forwarded->ulid],
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function preview(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        abort_unless($this->userMayForward($request->user(), $chatSession), 403);

        return response()->json([
            'subject'   => self::subject($chatSession),
            'forwarded' => self::forwardedBlock($chatSession),
        ]);
    }
}
