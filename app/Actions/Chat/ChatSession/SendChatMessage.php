<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\WithTimeSinceTaken;
use App\Actions\Comms\ChatEmailRecipient\StoreChatEmailRecipient;
use App\Actions\Comms\Email\SendChatNotificationToCustomer;
use App\Actions\Comms\Email\SendChatNotificationToExternal;
use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Actions\Comms\Mailbox\ImportPendingGmailAttachments;
use App\Actions\Comms\Mailbox\SendChatMessageByGmail;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentClaimedViaEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Events\BroadcastRealtimeChat;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use App\Services\Gmail\GmailClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SendChatMessage
{
    use WithTrustedChatWebUser;
    use WithChatAgentAuthorisation;
    use WithTimeSinceTaken;
    use AsAction;

    public const ATTACHMENT_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'pptx', 'zip', 'mp4', 'mov'];

    public const MAX_ATTACHMENTS = 30;

    public const MAX_AGENT_FILE_KB = 100 * 1024;

    public const MAX_CUSTOMER_FILE_BYTES = 10 * 1024 * 1024;

    public const MAX_CUSTOMER_DAILY_BYTES = 100 * 1024 * 1024;

    private const float KEPT_OWN_LANGUAGE = 0.5;

    public const int MAX_MEASURED_SECONDS = 1800;

    /**
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, array $modelData): ChatMessage
    {
        if ($chatSession->channel === ChatChannelEnum::EMAIL
            && $modelData['sender_type'] === ChatSenderTypeEnum::AGENT->value
            && (!$chatSession->shop || !GmailClient::isShopMailboxUsable($chatSession->shop))) {
            throw ValidationException::withMessages([
                'message_text' => __('This reply cannot be emailed: the shop mailbox is not connected. Reconnect it in the shop settings and send it again.'),
            ]);
        }

        $rawMessage = $modelData['message_text'] ?? '';
        if ($rawMessage !== null) {
            $sanitizedMessage = stripTagsKeepingEmailAddresses($rawMessage);
            $sanitizedMessage = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $sanitizedMessage);
            $sanitizedMessage = trim($sanitizedMessage);
        } else {
            $sanitizedMessage = null;
        }
        // A double click sends the same words twice; two pictures sent within a second share their
        // empty text, and treating the second as a repeat of the first threw its file away (INI-144).
        $carriesFiles = ($modelData['image'] ?? null) instanceof UploadedFile
            || ($modelData['file'] ?? null) instanceof UploadedFile
            || ! empty($modelData['attachments']);

        $exists = $carriesFiles ? null : ChatMessage::where('chat_session_id', $chatSession->id)
            ->where('sender_type', $modelData['sender_type'])
            ->where(fn ($query) => $query->where('message_text', $sanitizedMessage ?? '')->orWhere('original_text', $sanitizedMessage ?? ''))
            ->whereBetween('created_at', [now()->subSeconds(), now()])
            ->first();

        if ($exists) {
            return $exists;
        }


        $ownLanguage = $this->draftWrittenInOwnLanguage($chatSession, $modelData, $sanitizedMessage);

        $chatMessageData = [
            'chat_session_id' => $chatSession->id,
            'message_type'    => $modelData['message_type'] ?? ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => $modelData['sender_type'],
            'sender_id'       => $modelData['sender_id'] ?? null,
            'message_text'    => $ownLanguage['message_text'] ?? $sanitizedMessage,
            'media_id'        => $modelData['media_id'] ?? null,
            'original_text'   => $sanitizedMessage,
            'original_language_id' => $ownLanguage['original_language_id'] ?? null,
            'is_read'         => false,
            'metadata'        => $this->withTimeSinceTaken($chatSession, $this->withActiveTime($ownLanguage['metadata'] ?? $this->pageMetadata($chatSession, $modelData), $modelData), $modelData),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        $chatMessage = ChatMessage::create($chatMessageData);

        if ($ownLanguage) {
            $ownLanguage['draft']->update(['written_in_language_id' => $ownLanguage['staff_language_id']]);
        }

        if ($modelData['sender_type'] === ChatSenderTypeEnum::AGENT->value && !empty($modelData['sender_id'])) {
            SaveChatReplyDraft::make()->forget($chatSession, (int) $modelData['sender_id']);
        }

        if (isset($modelData['image']) && $modelData['image'] instanceof UploadedFile) {
            $this->processMessageImage($chatMessage, $modelData['image']);
        }
        if (isset($modelData['file']) && $modelData['file'] instanceof UploadedFile) {
            $this->processMessageFile($chatMessage, $modelData['file']);
        }
        if (! empty($modelData['attachments'])) {
            $this->processMessageAttachments($chatMessage, $modelData['attachments'], $modelData['attachment_content_ids'] ?? []);
        }

        ProcessChatMessageSideEffects::dispatch(
            $chatSession,
            $modelData['sender_type'],
            $modelData['sender_id'] ?? null,
            $chatMessage
        );

        $isEmailReply = $chatSession->channel === ChatChannelEnum::EMAIL && $modelData['sender_type'] === ChatSenderTypeEnum::AGENT->value;

        if (!$isEmailReply) {
            TranslateChatMessage::dispatch(messageId: $chatMessage->id);
        }

        BroadcastRealtimeChat::dispatch($chatMessage);
        BroadcastChatListEvent::dispatch($chatMessage);

        if ($isEmailReply) {
            $copies = SendChatMessageByGmail::copyRecipients($chatSession, $modelData['email_cc_excluded'] ?? []);

            $blindCopies = SendChatMessageByGmail::blindCopyRecipients($chatSession, $modelData['email_bcc'] ?? []);

            if ($copies || $blindCopies) {
                $chatMessage->updateQuietly([
                    'metadata' => array_merge(
                        $chatMessage->metadata ?? [],
                        $copies ? ['email_cc' => $copies] : [],
                        $blindCopies ? ['email_bcc' => $blindCopies] : [],
                    ),
                ]);
            }

            SendChatMessageByGmail::dispatch($chatMessage);
            ImportPendingGmailAttachments::dispatch($chatSession);
        }

        // On an email conversation the reply is itself the email, so a chat notification beside it
        // reaches the same inbox twice. The composers hide the switch, but the guard belongs here:
        // it is the one place every composer sends through.
        $shouldNotifyByEmail = ($modelData['is_email_notif'] ?? false)
            && $chatSession->channel !== ChatChannelEnum::EMAIL;

        $isAgentReply = $modelData['sender_type'] === ChatSenderTypeEnum::AGENT->value;

        $sentOfflineReplyWhatsapp = $isAgentReply && NotifyOfflineMessageReplyOnWhatsapp::run($chatSession);

        if ($shouldNotifyByEmail && $isAgentReply) {
            $this->sendExternalNotification($chatSession, notifyOnWhatsapp: !$sentOfflineReplyWhatsapp);
        }

        return $chatMessage;
    }

    /**
     * HELP-4534: an email reply the agent wrote on Aiku's draft translated into their own language.
     * Sent unchanged, the customer gets the draft as Aiku wrote and checked it, never a translation
     * of its translation; changed, it is translated into the language the draft was written in.
     *
     * A reply rewritten rather than edited may be in any language, so it is left to detection
     * like any other reply.
     *
     * @return array{message_text: string|null, original_language_id: int|null, metadata: array<string, int|null>, staff_language_id: int, draft: ChatAiDraft}|null
     */
    private function draftWrittenInOwnLanguage(ChatSession $chatSession, array $modelData, ?string $text): ?array
    {
        if ($chatSession->channel !== ChatChannelEnum::EMAIL
            || $modelData['sender_type'] !== ChatSenderTypeEnum::AGENT->value
            || empty($modelData['ai_draft_translation_of'])
            || blank($text)) {
            return null;
        }

        $draft = ChatAiDraft::where('id', $modelData['ai_draft_translation_of'])->where('chat_session_id', $chatSession->id)->first();
        $agent = ChatAgent::find($modelData['sender_id'] ?? null);

        $languageId  = HandleChatAiDraft::staffLanguageId($agent, $agent?->user);
        $translation = $draft && $languageId ? ($draft->staff_translations[$languageId] ?? null) : null;

        if (!$translation) {
            return null;
        }

        $asWritten = SettleChatAiDraft::normalised($translation) === SettleChatAiDraft::normalised($text)
            && !preg_match(DraftChatReply::GAP, $draft->text);

        $stillInOwnLanguage = $asWritten || SettleChatAiDraft::draftWordsKept($translation, $text) >= self::KEPT_OWN_LANGUAGE;

        return [
            'message_text'         => $asWritten ? $draft->text : null,
            'original_language_id' => $stillInOwnLanguage ? $languageId : null,
            'metadata'             => $asWritten
                ? ['draft_sent_as_written' => $draft->id]
                : ['reply_language_id' => HandleChatAiDraft::replyLanguageId($chatSession, $draft)],
            'staff_language_id'    => $languageId,
            'draft'                => $draft,
        ];
    }

    /**
     * The page the customer was looking at when they wrote, so the agent can answer "what is in
     * this?" without asking for the product code first (HELP-3982). A product or category page
     * also gives its code and title; any other page keeps only its address.
     */
    /**
     * What the inbox measured while staff worked on this reply (useActiveTime.ts), for the staff time
     * of the AI Assistant report. Only a staff reply carries it.
     */
    private function withTimeSinceTaken(ChatSession $chatSession, ?array $metadata, array $modelData): ?array
    {
        if (($modelData['sender_type'] ?? null) !== ChatSenderTypeEnum::AGENT->value) {
            return $metadata;
        }

        $sinceTaken = $this->timeSinceTaken($chatSession, isset($modelData['sender_id']) ? (int) $modelData['sender_id'] : null);

        return $sinceTaken ? [...($metadata ?? []), ...$sinceTaken] : $metadata;
    }

    /**
     * Measured seconds are kept only when they are numbers, clamped to MAX_MEASURED_SECONDS: a bad
     * figure from the page never costs the reply.
     *
     * @param  array{writing_seconds?: mixed, typing_seconds?: mixed}  $activeTime
     *
     * @return array{writing_seconds?: int, typing_seconds?: int}
     */
    public static function measuredTime(array $activeTime): array
    {
        $writing = $activeTime['writing_seconds'] ?? null;
        if (!is_numeric($writing)) {
            return [];
        }

        $typing = $activeTime['typing_seconds'] ?? null;

        return [
            'writing_seconds' => (int) min(max((float) $writing, 0), self::MAX_MEASURED_SECONDS),
            'typing_seconds'  => (int) min(max(is_numeric($typing) ? (float) $typing : 0, 0), self::MAX_MEASURED_SECONDS),
        ];
    }

    private function withActiveTime(?array $metadata, array $modelData): ?array
    {
        if (($modelData['sender_type'] ?? null) !== ChatSenderTypeEnum::AGENT->value || empty($modelData['active_time'])) {
            return $metadata;
        }

        $measured = self::measuredTime($modelData['active_time']);

        return $measured ? [...($metadata ?? []), ...$measured] : $metadata;
    }

    private function pageMetadata(ChatSession $chatSession, array $modelData): ?array
    {
        $pageUrl = $modelData['page_url'] ?? null;

        if (blank($pageUrl) || $modelData['sender_type'] === ChatSenderTypeEnum::AGENT->value) {
            return null;
        }

        $metadata = ['page_url' => $pageUrl];

        $website = $chatSession->shop?->website;
        $slug    = Str::afterLast(trim(rawurldecode((string) parse_url($pageUrl, PHP_URL_PATH)), '/'), '/');

        if (!$website || $slug === '') {
            return $metadata;
        }

        $webpageId = ShowIrisWebpage::make()->getWebpageID($website, $slug);
        $webpage   = $webpageId ? Webpage::find($webpageId) : null;

        if (!$webpage) {
            return $metadata;
        }

        return array_merge($metadata, array_filter([
            'page_title' => $webpage->title,
            'page_code'  => in_array($webpage->model_type, ['Product', 'ProductCategory']) ? $webpage->model?->code : null,
        ]));
    }

    protected function processMessageImage(ChatMessage $chatMessage, UploadedFile $file): void
    {
        $imageData = [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'checksum'     => md5_file($file->getPathName()),
        ];

        $media = StoreMediaFromFile::run($chatMessage, $imageData, 'chat_images', 'image');

        $chatMessage->updateQuietly([
            'media_id'     => $media->id,
            'message_type' => ChatMessageTypeEnum::IMAGE,
        ]);

        $chatMessage->refresh();
    }

    protected function processMessageFile(ChatMessage $chatMessage, UploadedFile $file): void
    {
        $fileData = [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'checksum'     => md5_file($file->getPathName()),
        ];

        $media = StoreMediaFromFile::run($chatMessage, $fileData, 'chat_attachments', 'file');

        $chatMessage->updateQuietly([
            'media_id'     => $media->id,
            'message_type' => ChatMessageTypeEnum::FILE,
        ]);

        $chatMessage->refresh();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string|null>  $contentIds  the Content-ID an email's markup addresses each file by, at the same position
     */
    public function processMessageAttachments(ChatMessage $chatMessage, array $files, array $contentIds = []): void
    {
        $firstMediaId = $chatMessage->media_id;
        $allImages    = true;

        foreach ($files as $index => $file) {
            $isImage   = str_starts_with((string) $file->getMimeType(), 'image/');
            $allImages = $allImages && $isImage;

            $media = StoreMediaFromFile::run($chatMessage, [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'checksum'     => md5_file($file->getPathName()),
            ], $isImage ? 'chat_images' : 'chat_attachments', $isImage ? 'image' : 'file');

            if ($contentIds[$index] ?? null) {
                $media->setCustomProperty('content_id', $contentIds[$index])->save();
            }

            $firstMediaId ??= $media->id;
        }

        $chatMessage->updateQuietly([
            'media_id'     => $firstMediaId,
            'message_type' => $allImages && $chatMessage->message_type !== ChatMessageTypeEnum::FILE ? ChatMessageTypeEnum::IMAGE : ChatMessageTypeEnum::FILE,
        ]);

        $chatMessage->refresh();
    }

    protected function updateSessionTimestamps(ChatSession $chatSession, string $senderType): void
    {
        $updateData = [];

        if ($senderType === ChatSenderTypeEnum::GUEST->value || $senderType === ChatSenderTypeEnum::USER->value) {
            data_set($updateData, 'last_visitor_message_at', now());
        } elseif ($senderType === ChatSenderTypeEnum::AGENT->value) {
            data_set($updateData, 'last_agent_message_at', now());
        }

        if (!empty($updateData)) {
            $chatSession->update($updateData);
        }
    }

    protected function logMessageEvent(ChatSession $chatSession, string $senderType, ?int $senderId, ChatMessage $message): void
    {
        $actorType = match ($senderType) {
            ChatActorTypeEnum::AGENT->value => ChatActorTypeEnum::AGENT,
            ChatSenderTypeEnum::USER->value => ChatActorTypeEnum::USER,
            default => ChatActorTypeEnum::GUEST
        };

        $isGuestMessage = in_array($senderType, [ChatActorTypeEnum::GUEST->value, ChatActorTypeEnum::USER->value]);

        StoreChatEvent::make()->messageSent(
            $chatSession,
            $actorType,
            $senderId,
            $message->id,
            $message->message_type->value,
            $isGuestMessage
        );
    }

    /**
     * @throws \Throwable
     */
    protected function sendExternalNotification(ChatSession $chatSession, bool $notifyOnWhatsapp = true): void
    {
        $chatLink = NotifyOfflineMessageReplyOnWhatsapp::chatLink($chatSession);
        if ($chatSession->web_user_id && $chatSession->webUser && $chatSession->webUser->customer) {
            SendChatNotificationToCustomer::dispatch($chatSession->webUser->customer, ['chat_link' => $chatLink,
            'chat_message' => $chatSession->messages()?->latest()?->first()?->message_text ?? null,
            ]);
            if ($notifyOnWhatsapp) {
                SendCustomerNotificationWhatsapp::dispatch(
                    $chatSession->webUser->customer,
                    OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER,
                    null,
                    ['chat_url' => $chatLink]
                );
            }

            return;
        }

        if (!$chatSession->shop) {
            return;
        }

        $metadata = $chatSession->metadata ?? [];

        $email = $metadata['email'] ?? null;
        $name  = $metadata['name'] ?? null;

        if (!$email) {
            return;
        }


        $chatEmailRecipient = $chatSession->shop->chatEmailRecipients()->where('email', $email)->first();
        if ($chatEmailRecipient && $name) {
            $chatEmailRecipient->update([
                'name' => $name,
            ]);
        }

        if (!$chatEmailRecipient) {
            $chatEmailRecipient = StoreChatEmailRecipient::make()->action($chatSession->shop, [
                'name'  => $name,
                'email' => $email,
            ]);
        }

        SendChatNotificationToExternal::dispatch($chatEmailRecipient, $chatSession->shop, ['chat_link' => $chatLink,
         'chat_message' => $chatSession->messages()?->latest()?->first()?->message_text ?? null]);
    }

    public function rules(): array
    {
        return [
            'message_text'   => [
                'required_without_all:image,file,attachments',
                'nullable',
                'string',
                Auth::user() instanceof User ? 'max:100000' : 'max:5000',
                fn (string $attribute, mixed $value, \Closure $fail) => is_string($value) && preg_match(\App\Actions\Chat\ChatSession\DraftChatReply::GAP, $value)
                    ? $fail(__('Fill in or delete the parts marked [[ ]] before sending.'))
                    : null,
            ],
            'message_type'   => [
                'required',
                Rule::enum(ChatMessageTypeEnum::class)
            ],
            'sender_id'      => [
                'nullable',
                'integer',
                Rule::exists('web_users', 'id'),
            ],
            'sender_type'    => [
                'sometimes',
                Rule::enum(ChatSenderTypeEnum::class)
            ],
            'image'          => [
                'sometimes',
                'nullable',
                File::image()->max(self::MAX_AGENT_FILE_KB)
            ],
            'file'           => [
                'sometimes',
                'nullable',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'pptx', 'zip', 'mp4', 'mov'])
                    ->max(self::MAX_AGENT_FILE_KB)
            ],
            'attachments'    => [
                'sometimes',
                'array',
                'max:'.self::MAX_ATTACHMENTS,
            ],
            'attachments.*'  => [
                File::types(self::ATTACHMENT_TYPES)
                    ->max(self::MAX_AGENT_FILE_KB)
            ],
            'attachment_content_ids'   => [
                'sometimes',
                'array',
            ],
            'attachment_content_ids.*' => [
                'nullable',
                'string',
                'regex:/^[A-Za-z0-9-]{1,64}$/',
            ],
            'is_email_notif' => [
                'sometimes',
                'nullable',
                'in:true,false'
            ],
            'email_cc_excluded'   => [
                'sometimes',
                'array',
            ],
            'ai_draft_translation_of' => [
                'sometimes',
                'nullable',
                'integer',
            ],
            'page_url'       => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:2048',
            ],
            'email_cc_excluded.*' => [
                'string',
                'max:255',
            ],
        ];
    }


    /**
     * A reply written for the signed in agent outside the composer, by their AI assistant. It goes
     * through the same door as the composer: the same text rules, and the chat is claimed when
     * nobody holds it and refused when a colleague does.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function replyAsAgent(ChatSession $chatSession, string $text): ChatMessage
    {
        $validated = validator([
            'message_text' => $text,
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::AGENT->value,
        ], Arr::only($this->rules(), ['message_text', 'message_type', 'sender_type']))->validate();

        $sender = $this->determineSenderData($validated, $chatSession);

        if (!$sender['ok']) {
            throw ValidationException::withMessages(['message_text' => $sender['message']]);
        }

        return $this->handle($chatSession, array_merge($validated, $sender['data']));
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Request $request): array
    {
        /** @var ChatSession|string $chatSession */
        $chatSession = $request->route('chatSession');

        $ulid = is_string($chatSession)
            ? $chatSession
            : $chatSession->ulid;


        $this->validateUlid($ulid);

        $validated = $request->validate($this->rules());

        $chatSession = ChatSession::where('ulid', $ulid)->firstOrFail();

        if ($request->routeIs('grp.org.chat.agents.messages.send')) {
            abort_unless(Arr::get($validated, 'sender_type') === ChatSenderTypeEnum::AGENT->value, 403);
            abort_unless($chatSession->shop instanceof Shop && $this->userCanActOnChatOnShop($request->user(), $chatSession->shop), 403);
            $validated['active_time'] = ['writing_seconds' => $request->input('writing_seconds'), 'typing_seconds' => $request->input('typing_seconds')];
        }

        $senderResult = $this->determineSenderData($validated, $chatSession);

        if (!$senderResult['ok']) {
            return $senderResult;
        }

        $validated = array_merge($validated, $senderResult['data']);

        if ($validated['sender_type'] !== ChatSenderTypeEnum::AGENT->value) {
            $refusal = $this->customerUploadRefusal($request);

            if ($refusal) {
                return $refusal;
            }
        }

        $chatMessage = $this->handle($chatSession, $validated);

        return [
            'ok'   => true,
            'data' => $chatMessage,
            'code' => 201,
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateUlid($ulid): void
    {
        validator(
            ['session_ulid' => $ulid],
            $this->ulidRules()
        )->validate();
    }


    protected function ulidRules(): array
    {
        return [
            'session_ulid' => [
                'required',
                'string',
                'ulid',
                Rule::exists('chat_sessions', 'ulid')
            ]
        ];
    }


    /**
     * Writing into a conversation nobody holds is how an agent picks it up: a customer who
     * answers a closed email thread comes back to the waiting queue unassigned, and every
     * composer in the app used to refuse the reply with no way to claim it from there.
     * A conversation somebody else is holding still has to be taken over on purpose.
     *
     * @return array{ok: bool, message: string, code: int}|null  a refusal, or null once claimed
     */
    public function claimUnheldChat(ChatSession $chatSession, User $user, ChatAgent $agent): ?array
    {
        $holderId = $chatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->value('chat_agent_id');

        if ($holderId) {
            return $this->refuseUnlessHeldBy($chatSession, $agent);
        }

        $shop = $chatSession->shop;

        if (!$shop instanceof Shop || !$this->userCanActOnChatOnShop($user, $shop)) {
            return [
                'ok'      => false,
                'message' => __('You do not work chat on this shop.'),
                'code'    => 403,
            ];
        }

        try {
            $chatSession->assignments()->create([
                'chat_agent_id' => $agent->id,
                'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
                'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
                'note'          => 'Claimed by replying',
                'claimed_via'   => ChatAssignmentClaimedViaEnum::REPLY,
                'assigned_at'   => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->refuseUnlessHeldBy($chatSession, $agent);
        }

        $chatSession->update([
            'status'    => ChatSessionStatusEnum::ACTIVE->value,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        ChatAgentHydrateChats::run($agent);

        return null;
    }

    /**
     * Two replies typed in quick succession both try to pick the chat up; the one that loses
     * to its own sender's first reply goes through instead of being told to take it over.
     *
     * @return array{ok: bool, message: string, code: int}|null
     */
    private function refuseUnlessHeldBy(ChatSession $chatSession, ChatAgent $agent): ?array
    {
        $isHeldByAgent = $chatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->where('chat_agent_id', $agent->id)
            ->exists();

        if ($isHeldByAgent) {
            return null;
        }

        return [
            'ok'      => false,
            'message' => $this->chatHeldByAnotherAgentMessage($chatSession),
            'code'    => 403,
        ];
    }

    /**
     * Agents may send up to 100MB, but anyone on the website can post here, so customer files
     * are kept small and each IP gets a daily allowance: a script posting files in a loop fills
     * its allowance, not our disk.
     *
     * @return array{ok: false, message: string, code: int}|null
     */
    public function customerUploadRefusal(Request $request): ?array
    {
        $files = array_filter([$request->file('image'), $request->file('file'), ...($request->file('attachments') ?? [])]);

        if (!$files) {
            return null;
        }

        $bytes = array_sum(array_map(fn (UploadedFile $file) => $file->getSize(), $files));

        foreach ($files as $file) {
            if ($file->getSize() > self::MAX_CUSTOMER_FILE_BYTES) {
                return ['ok' => false, 'message' => 'Maximum file size 10MB', 'code' => 422];
            }
        }

        $key = 'chat-customer-upload-bytes:'.$request->ip();

        if (RateLimiter::attempts($key) + $bytes > self::MAX_CUSTOMER_DAILY_BYTES) {
            return ['ok' => false, 'message' => 'Upload limit reached, please try again tomorrow', 'code' => 429];
        }

        RateLimiter::increment($key, 86400, $bytes);

        return null;
    }

    private function isAuthenticatedChatAgent(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ChatAgent::where('user_id', $user->id)->exists();
    }

    protected function determineSenderData(array $validated, ChatSession $chatSession): array
    {
        $senderType = $validated['sender_type'] ?? null;

        if ($senderType === ChatSenderTypeEnum::SYSTEM->value && $this->isAuthenticatedChatAgent()) {
            return [
                'ok'   => true,
                'data' => [
                    'sender_type' => ChatSenderTypeEnum::SYSTEM->value,
                    'sender_id'   => null,
                ],
            ];
        }
        if ($senderType === ChatSenderTypeEnum::AGENT->value) {
            $user = Auth::user();

            if (!$user instanceof User) {
                return [
                    'ok'      => false,
                    'message' => 'Only authenticated agents can send chats',
                    'code'    => 403,
                ];
            }

            $agent = ChatAgent::where('user_id', $user->id)->first();

            if (!$agent) {
                return [
                    'ok'      => false,
                    'message' => 'Only agents can send messages.',
                    'code'    => 403,
                ];
            }

            $isAssigned = $chatSession->assignments()
                ->where('chat_agent_id', $agent->id)
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->exists();

            if (!$isAssigned) {
                $claim = $this->claimUnheldChat($chatSession, $user, $agent);

                if ($claim !== null) {
                    return $claim;
                }
            }

            return [
                'ok'   => true,
                'data' => [
                    'sender_type' => ChatSenderTypeEnum::AGENT->value,
                    'sender_id'   => $agent->id,
                ],
            ];
        }

        if (!empty($validated['sender_id']) && $senderType === ChatSenderTypeEnum::USER->value) {
            $webUserId = $this->trustedWebUserId($validated['sender_id']);
            $webUser   = $webUserId ? WebUser::find($webUserId) : null;

            if ($webUser) {
                $chatSession->update([
                    'web_user_id' => $webUser->id,
                    'updated_at'  => now(),
                ]);

                return [
                    'ok'   => true,
                    'data' => [
                        'sender_type' => ChatSenderTypeEnum::USER->value,
                        'sender_id'   => $webUser->id,
                    ],
                ];
            }
        }

        if ($senderType === ChatSenderTypeEnum::GUEST->value && $chatSession->web_user_id) {
            if ($chatSession->web_user_id) {
                return [
                    'ok'   => true,
                    'data' => [
                        'sender_type' => ChatSenderTypeEnum::USER->value,
                        'sender_id'   => $chatSession->web_user_id,
                    ],
                ];
            }
        }

        return [
            'ok'   => true,
            'data' => [
                'sender_type' => ChatSenderTypeEnum::GUEST->value,
                'sender_id'   => null,
            ],
        ];
    }


    public function jsonResponse($result): JsonResponse
    {
        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 400);
        }

        /** @var ChatMessage $message */
        $message = $result['data'];

        return response()->json([
            'success'    => true,
            'message'    => 'Message sent successfully',
            'message_id' => $message->id,
        ], $result['code'] ?? 200);
    }
}
