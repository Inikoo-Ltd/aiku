<?php

namespace App\Http\Resources\CRM\Livechat;

use App\Actions\Chat\UI\FlagChatAiSummary;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Enums\CRM\Livechat\ChatRetractionReasonEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    use HasSelfCall;

    /**
     * Metadata this resource never puts on the wire. The customer's widget reads the same
     * resource as the inbox does, and it is also what goes out over the public session
     * channel, so what was said before an edit and why a message was taken back are kept
     * out of it whoever is asking.
     *
     * @var array<int, string>
     */
    public const PRIVATE_METADATA = ['edit_history', 'retraction_note', 'flagged_reason', 'flagged_by_user_id', FlagChatAiSummary::KEY];

    /** A picture small enough to be written into an email's body instead of stored as a file. */
    public const EMBEDDED_PICTURE = '/<img\b[^>]*\ssrc="data:[^"]*"[^>]*>/i';

    public function toArray($request): array
    {
        $chatMessage = $this;

        $translations = $chatMessage->translations;

        $files = $chatMessage->attachedFiles();

        [$htmlBody, $inlineMediaIds] = $this->resolveEmailPictures($chatMessage->html_body, $files);

        return [
            'id' => $chatMessage->id,
            'message_text' => $chatMessage->message_text,
            // Already purified when it was stored, and shown inside a sandboxed frame. The text
            // above stays the message of record: it is what search and translation read.
            'html_body' => $htmlBody,
            'original' => [
                'text'          => $chatMessage->original_text,
                'language_name' => $chatMessage->originalLanguage?->name,
                'language_code' => $chatMessage->originalLanguage?->code,
                'language_flag' => $chatMessage->originalLanguage?->flag ? asset('flags/' . $chatMessage->originalLanguage->flag) : null,
            ],

            'translations' => $translations->map(function ($translation) {
                return [
                    'chat_translation_id' => $translation->id,
                    'language_id'     => $translation->targetLanguage?->id,
                    'translated_text' => $translation->translated_text,
                    'language_name'   => $translation->targetLanguage?->name,
                    'language_code'   => $translation->targetLanguage?->code,
                    'language_flag'   => $translation->targetLanguage?->flag ? asset('flags/' . $translation->targetLanguage->flag) : null,
                ];
            })->values(),

            'message_type' => $chatMessage->message_type->value,
            'sender_type' => $chatMessage->sender_type->value,
            'sender_name' => $chatMessage->sender_name,
            'is_agent' => $chatMessage->sender_type->value === ChatSenderTypeEnum::AGENT->value,
            'is_guest' => $chatMessage->sender_type->value === ChatSenderTypeEnum::GUEST->value,
            'is_user' => $chatMessage->sender_type->value === ChatSenderTypeEnum::USER->value,
            'is_system' => $chatMessage->sender_type->value === ChatSenderTypeEnum::SYSTEM->value,
            'is_ai' => $chatMessage->sender_type->value === ChatSenderTypeEnum::AI->value,
            'is_read' => $chatMessage->is_read,
            'is_rescued_from_spam' => $chatMessage->is_rescued_from_spam,
            'spam_rescue_kind_label' => $chatMessage->spam_rescue_kind?->label(),
            'is_possible_scam' => $chatMessage->is_possible_scam,
            'is_redacted' => isset($chatMessage->metadata['redacted_at']),
            'is_attachment_redacted' => isset($chatMessage->metadata['attachment_redacted_at']),
            'is_retracted' => $chatMessage->trashed(),
            'retracted_at' => $chatMessage->deleted_at?->toISOString(),
            'retraction_reason' => ChatRetractionReasonEnum::tryFrom(
                (string) ($chatMessage->metadata['retraction_reason'] ?? '')
            )?->label(),
            'is_ai_generated' => $chatMessage->is_ai_generated,
            'is_validated' => $chatMessage->is_validated,
            'is_verifiable_image' => $chatMessage->isVerifiableCustomerImage(),
            'ai_verification' => $chatMessage->metadata['ai_verification'] ?? null,
            'media_url' => $chatMessage->attachment?->getCustomProperty('archived_at') ? null : $chatMessage->imageSources(0, 0, 'attachment'),
            'original_url' => $chatMessage->attachment ? ($chatMessage->attachment->getCustomProperty('archived_at') ? route('grp.api.chats.chat.attachment.download', ['ulid' => $chatMessage->attachment->ulid]) : $chatMessage->attachment->getUrl()) : null,
            'file_name' => $chatMessage->attachment ? $chatMessage->attachment->file_name : null,
            'file_size' => $chatMessage->attachment ? $chatMessage->attachment->size : null,
            'file_mime' => $chatMessage->attachment ? $chatMessage->attachment->mime_type : null,
            'download_route' => $chatMessage->attachment ? [
                'name'       => 'grp.api.chats.chat.attachment.download',
                'parameters' => [
                    'ulid' => $chatMessage->attachment->ulid,
                ],
                'method'     => 'get',
                'url'        => route('grp.api.chats.chat.attachment.download', ['ulid' => $chatMessage->attachment->ulid])
            ] : null,
            'attachments' => $files->map(function ($media) use ($inlineMediaIds) {
                $isArchived = (bool) $media->getCustomProperty('archived_at');
                $isImage    = str_starts_with((string) $media->mime_type, 'image/') && !$isArchived;
                $download   = route('grp.api.chats.chat.attachment.download', ['ulid' => $media->ulid]);

                return [
                    'id'             => $media->id,
                    'is_image'       => $isImage,
                    'is_archived'    => $isArchived,
                    'is_inline'      => in_array($media->id, $inlineMediaIds, true),
                    'media_url'      => $isImage ? GetPictureSources::run($media->getImage()->resize(0, 0)) : null,
                    'original_url'   => $isArchived ? $download : $media->getUrl(),
                    'file_name'      => $media->name ?: $media->file_name,
                    'file_size'      => $media->size,
                    'file_mime'      => $media->mime_type,
                    'download_route' => [
                        'name'       => 'grp.api.chats.chat.attachment.download',
                        'parameters' => ['ulid' => $media->ulid],
                        'method'     => 'get',
                        'url'        => $download,
                    ],
                ];
            })->values(),
            'has_embedded_pictures' => (bool) preg_match(self::EMBEDDED_PICTURE, (string) $htmlBody),
            'reactions' => $chatMessage->reactions
                ->groupBy('emoji')
                ->map(function ($group, $emoji) {
                    return [
                        'emoji'    => $emoji,
                        'count'    => $group->count(),
                        'reactors' => $group->map(function ($reaction) {
                            return [
                                'type' => $reaction->reactor_type,
                                'id'   => $reaction->reactor_id,
                            ];
                        })->values(),
                    ];
                })->values(),
            'metadata' => Arr::except($chatMessage->metadata ?? [], self::PRIVATE_METADATA),
            'ai_summary_flagged' => FlagChatAiSummary::isFlagged($this->resource),
            'is_offline_message' => $chatMessage->metadata['is_offline_message'] ?? false,
            'edited_at' => $chatMessage->edited_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'timestamp' => $chatMessage->created_at->timestamp
        ];
    }

    /**
     * An email addresses its pictures by the Content-ID they travel under (cid:...), and mail
     * stored between 22 and 28 Sep 2026 by the file's path on our disk. A browser can fetch
     * neither, so each is pointed, at the time of showing, at wherever the file is served from
     * now: the picture service, or the download route once the file has been archived. A picture
     * whose file is gone, redacted or not fetched yet is left out rather than shown broken.
     *
     * @param  Collection<int, Media>  $files
     * @return array{0: ?string, 1: array<int, int>}
     */
    private function resolveEmailPictures(?string $html, Collection $files): array
    {
        if (! $html) {
            return [$html, []];
        }

        $byReference = [];
        foreach ($files as $media) {
            $byReference['file:'.strtolower((string) $media->file_name)] = $media;

            if ($contentId = $media->getCustomProperty('content_id')) {
                $byReference['cid:'.strtolower($contentId)] = $media;
            }
        }

        $inlineMediaIds = [];

        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $tag) use ($byReference, &$inlineMediaIds) {
            if (! preg_match('/\ssrc="([^"]*)"/i', $tag[0], $source)) {
                return $tag[0];
            }

            $address = rawurldecode(html_entity_decode($source[1]));

            if (preg_match('#^(https?:|data:)#i', $address)) {
                return $tag[0];
            }

            $reference = str_starts_with(strtolower($address), 'cid:')
                ? strtolower($address)
                : 'file:'.strtolower(basename($address));

            $media = $byReference[$reference] ?? null;

            if (! $media) {
                return '';
            }

            $inlineMediaIds[] = $media->id;

            $resolved = str_replace($source[0], ' src="'.e($this->pictureAddress($media)).'"', $tag[0]);

            return preg_replace_callback('/\salt="cid:[^"]*"/i', fn () => ' alt="'.e((string) $media->name).'"', $resolved);
        }, $html);

        return [$html, array_values(array_unique($inlineMediaIds))];
    }

    private function pictureAddress(Media $media): string
    {
        if (str_starts_with((string) $media->mime_type, 'image/') && ! $media->getCustomProperty('archived_at')) {
            return GetPictureSources::run($media->getImage()->resize(0, 0))['original'];
        }

        return route('grp.api.chats.chat.attachment.download', ['ulid' => $media->ulid, 'inline' => 1]);
    }
}
