<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Comms\Mailbox\ImportPendingGmailAttachments;
use App\Actions\Comms\Mailbox\SendChatMessageByGmail;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Sends the whole conversation, as text, to any address: a supplier, a courier, a colleague
 * outside Aiku. It goes out as a new email conversation from the shop's mailbox, so an answer
 * to it comes back to the inbox. The files go with it (HELP-4681): a courier's statement is the
 * attachment, not the covering text. When an agent forwards, files still waiting in Gmail are
 * brought in first; a file an email reply cannot carry is only named.
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

        if ($chatSession->shop instanceof Shop && $this->userCanActOnChatOnShop($user, $chatSession->shop)) {
            try {
                ImportPendingGmailAttachments::make()->handle($chatSession);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $files = [];

        try {
            foreach (self::forwardedFiles($chatSession) as $mediaId => $media) {
                $file = self::temporaryCopy($media);

                if ($file) {
                    $files[$mediaId] = $file;
                }
            }

            $forwarded = StartCustomerEmailChat::make()->action($chatSession->shop, [
                'email'       => $modelData['email'],
                'subject'     => $subject,
                'message'     => self::message($chatSession, $note, array_keys($files)),
                'attachments' => array_values($files),
            ]);
        } finally {
            foreach ($files as $file) {
                @unlink($file->getPathname());
            }
        }

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

    /**
     * A copy the email's own upload rule accepts, or null: a file gone from storage or from the
     * archive, or one that rule refuses, is named in the text instead of failing the whole forward.
     */
    private static function temporaryCopy(Media $media): ?UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'forward-attachment-');

        try {
            file_put_contents($path, GetChatMediaContents::run($media));

            $name = $media->name ?: $media->file_name;
            if (pathinfo($name, PATHINFO_EXTENSION) === '') {
                $name .= '.'.pathinfo($media->file_name, PATHINFO_EXTENSION);
            }

            $file = new UploadedFile($path, $name, $media->mime_type, null, true);

            if (Validator::make(['file' => $file], ['file' => [File::types(SendChatMessage::ATTACHMENT_TYPES)->max(SendChatMessage::MAX_AGENT_FILE_KB)]])->passes()) {
                return $file;
            }
        } catch (Throwable $e) {
            report($e);
        }

        @unlink($path);

        return null;
    }

    public static function subject(ChatSession $chatSession): string
    {
        $subject = trim((string) Arr::get($chatSession->metadata ?? [], 'email_subject'))
            ?: __('Conversation :ulid', ['ulid' => $chatSession->ulid]);

        return 'Fwd: '.$subject;
    }

    /**
     * @param  array<int, int>|null  $attached  ids of the files going with the forward; null = the ones that would
     */
    public static function message(ChatSession $chatSession, string $note, ?array $attached = null): string
    {
        $forwarded = self::forwardedBlock($chatSession, $attached);

        return $note === '' ? $forwarded : $note."\n\n".$forwarded;
    }

    /**
     * @param  array<int, int>|null  $attached  ids of the files going with the forward; null = the ones that would
     */
    public static function forwardedBlock(ChatSession $chatSession, ?array $attached = null): string
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
        $attached ??= self::forwardedFiles($chatSession)->keys()->all();

        $entries = $messages
            ->map(fn (ChatMessage $message) => self::entry($chatSession, $message, $agents, $timezone, $attached))
            ->filter()
            ->all();

        return implode("\n\n", [
            self::SEPARATOR,
            __('Subject: :subject', ['subject' => Arr::get($chatSession->metadata ?? [], 'email_subject') ?: '-']),
            ...$entries,
        ]);
    }

    /**
     * The conversation's files an email can carry, keyed by media id, oldest first: as many as an
     * email reply takes and no more than Gmail attaches (18MB), so a forward never reads, copies
     * and stores files only to send them as links. Files of mail rescued from spam stay out, as
     * its held files do.
     *
     * @return Collection<int, Media>
     */
    public static function forwardedFiles(ChatSession $chatSession): Collection
    {
        $types = [...SendChatMessage::ATTACHMENT_TYPES, 'jpg', 'jpeg'];
        $bytes = 0;

        return $chatSession->messages()
            ->with('media')
            ->where('sender_type', '!=', ChatSenderTypeEnum::SYSTEM->value)
            ->where('is_rescued_from_spam', false)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (ChatMessage $message) => $message->attachedFiles())
            ->filter(function (Media $media) use ($types, &$bytes) {
                if ($media->size <= 0
                    || $bytes + $media->size > SendChatMessageByGmail::MAX_ATTACHED_BYTES
                    || ! in_array(MimeTypes::getDefault()->getExtensions((string) $media->mime_type)[0] ?? null, $types, true)) {
                    return false;
                }

                $bytes += $media->size;

                return true;
            })
            ->take(SendChatMessage::MAX_ATTACHMENTS)
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, ChatAgent>  $agents
     * @param  array<int, int>  $attached  ids of the files going with the forward
     */
    private static function entry(ChatSession $chatSession, ChatMessage $message, Collection $agents, string $timezone, array $attached): ?string
    {
        $text  = trim((string) ($message->original_text ?? $message->message_text));
        $files = $message->attachedFiles();

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

        [$sent, $notSent] = $files->partition(fn (Media $media) => in_array($media->id, $attached, true))
            ->map(fn (Collection $group) => $group->map(fn (Media $media) => $media->name ?: $media->file_name)->filter()->values())
            ->all();

        if ($sent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = __('Attachments: :files', ['files' => $sent->join(', ')]);
        }

        if ($notSent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = __('Attachments not forwarded: :files', ['files' => $notSent->join(', ')]);
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

        if (in_array($message->sender_type, [ChatSenderTypeEnum::AI, ChatSenderTypeEnum::SYSTEM_CAMPAIGN, ChatSenderTypeEnum::SYSTEM_NOTIFICATION], true)) {
            return $shopName;
        }

        $from    = Arr::get($message->metadata ?? [], 'email_from', []);
        $address = Arr::get($from, 'address') ?: Arr::get($chatSession->metadata ?? [], 'email');
        $name    = Arr::get($from, 'name')
            ?: Arr::get($chatSession->metadata ?? [], 'name')
            ?: $chatSession->webUser?->customer?->contact_name;

        // Not "Name <address>": the message is stored through SendChatMessage, which strips tags,
        // and "Name (address)" reads the same without depending on what it lets through.
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
