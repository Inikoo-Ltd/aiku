<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\ChatSession;
use App\Models\Helpers\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every picture and file of one conversation, newest first, for the gallery in the Customer
 * Inbox. The urls are the ones the message bubbles use, so a file opens the same way from both.
 */
class GetChatSessionFiles
{
    use WithChatSessionReadAuthorisation;
    use AsAction;

    private const MAX_ITEMS = 500;

    private const COLLECTIONS = ['chat_images', 'chat_attachments'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(ChatSession|MetaChatSession $session): array
    {
        $messages = $session instanceof MetaChatSession
            ? $this->metaMessages($session)
            : $this->chatMessages($session);

        $senderNames = $this->agentNames($messages);

        return $messages
            ->flatMap(function (ChatMessage|MetaChatMessage $message) use ($senderNames) {
                $from = $this->from($message);

                if ($from === null) {
                    return [];
                }

                return $this->files($message)->map(fn (Media $media) => $this->item($message, $media, $from, $senderNames))->all();
            })
            ->take(self::MAX_ITEMS)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function chatMessages(ChatSession $session): Collection
    {
        return ChatMessage::query()
            ->where('chat_session_id', $session->id)
            ->whereHas('media', fn ($query) => $query->whereIn('collection_name', self::COLLECTIONS))
            ->with('media')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ITEMS)
            ->get()
            ->reject(fn (ChatMessage $message) => isset($message->metadata['attachment_redacted_at']))
            ->values();
    }

    /**
     * @return Collection<int, MetaChatMessage>
     */
    private function metaMessages(MetaChatSession $session): Collection
    {
        return MetaChatMessage::query()
            ->where('meta_chat_session_id', $session->id)
            ->where(function ($query) {
                $query->whereNotNull('media_id')
                    ->orWhereHas('media', fn ($media) => $media->whereIn('collection_name', self::COLLECTIONS));
            })
            ->with(['attachment', 'media'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ITEMS)
            ->get();
    }

    /**
     * @return Collection<int, Media>
     */
    private function files(ChatMessage|MetaChatMessage $message): Collection
    {
        $files = $message->media->whereIn('collection_name', self::COLLECTIONS);

        if ($message instanceof MetaChatMessage && $message->attachment) {
            $files = $files->push($message->attachment);
        }

        return $files->unique('id')->sortBy('order_column')->values();
    }

    private function from(ChatMessage|MetaChatMessage $message): ?string
    {
        return match ($message->sender_type) {
            ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST => 'customer',
            ChatSenderTypeEnum::AGENT, ChatSenderTypeEnum::AI => 'agent',
            default => null,
        };
    }

    /**
     * @param  Collection<int, ChatMessage|MetaChatMessage>  $messages
     * @return array<int, string>
     */
    private function agentNames(Collection $messages): array
    {
        $agentIds = $messages
            ->filter(fn ($message) => $message->sender_type === ChatSenderTypeEnum::AGENT && $message->sender_id)
            ->pluck('sender_id')
            ->unique()
            ->values();

        if ($agentIds->isEmpty()) {
            return [];
        }

        return ChatAgent::with('user')
            ->whereIn('id', $agentIds)
            ->get()
            ->mapWithKeys(fn (ChatAgent $agent) => [$agent->id => $agent->user?->contact_name ?: $agent->user?->username])
            ->filter()
            ->all();
    }

    /**
     * @param  array<int, string>  $senderNames
     * @return array<string, mixed>
     */
    private function item(ChatMessage|MetaChatMessage $message, Media $media, string $from, array $senderNames): array
    {
        $isArchived = (bool) $media->getCustomProperty('archived_at');
        $isImage    = str_starts_with((string) $media->mime_type, 'image/') && !$isArchived;
        $download   = route('grp.api.chats.chat.attachment.download', ['ulid' => $media->ulid]);

        return [
            'id'          => $media->id,
            'message_id'  => $message->id,
            'from'        => $from,
            'sender_name' => $message->sender_type === ChatSenderTypeEnum::AGENT ? ($senderNames[$message->sender_id] ?? null) : null,
            'created_at'  => $message->created_at?->toISOString(),
            'is_image'    => $isImage,
            'is_archived' => $isArchived,
            'thumbnail'   => $isImage ? GetPictureSources::run($media->getImage()->resize(400, 400)) : null,
            'full'        => $isImage ? GetPictureSources::run($media->getImage()->resize(0, 0)) : null,
            'download_url' => $download,
            'open_url'    => $isArchived ? $download : $media->getUrl(),
            'file_name'   => $media->name ?: $media->file_name,
            'file_size'   => $media->size,
            'file_mime'   => $media->mime_type,
        ];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->handle($chatSession)]);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        $request->validate([]);

        return response()->json(['data' => $this->handle($metaChatSession)]);
    }
}
