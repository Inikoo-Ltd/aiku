<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Events\BroadcastRealtimeChat;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Strike a fragment out of a message that should never have been written down — a card
 * number, a password, somebody else's address. The fragment is replaced everywhere it was
 * stored, for good: the message itself, the text it was translated from, and every
 * translation of it.
 *
 * Redaction only ever removes. The agent says which characters go and they are replaced
 * with blocks of the same length, so nobody can use this to put words in a customer's
 * mouth, and the shape of what was taken out stays visible.
 */
class RedactChatMessage
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const MASK = '█';

    /**
     * Every occurrence goes, because a card number is often written twice. That makes a
     * careless selection dangerous: one letter would be struck out of the whole message
     * and there is no way back. Short enough to be a slip, so short is refused.
     */
    public const MINIMUM_FRAGMENT = 3;

    public function rules(): array
    {
        return [
            'fragment' => ['required', 'string', 'min:'.self::MINIMUM_FRAGMENT, 'max:10000'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ChatMessage $chatMessage, ChatAgent $agent, string $fragment): ChatMessage
    {
        if ($chatMessage->chat_session_id !== $chatSession->id) {
            throw ValidationException::withMessages([
                'message' => __('Message does not belong to this chat session'),
            ]);
        }

        // Guarded here rather than only in the request rules, so it holds for every caller.
        if (mb_strlen($fragment) < self::MINIMUM_FRAGMENT) {
            throw ValidationException::withMessages([
                'message' => __('Select at least :count characters to strike out', ['count' => self::MINIMUM_FRAGMENT]),
            ]);
        }

        $occurrences = $this->countOccurrences($chatMessage, $fragment);

        if ($occurrences === 0) {
            throw ValidationException::withMessages([
                'message' => __('That text is not in this message'),
            ]);
        }

        $mask = str_repeat(self::MASK, mb_strlen($fragment));

        DB::transaction(function () use ($chatMessage, $agent, $fragment, $mask, $occurrences) {
            $metadata = $chatMessage->metadata ?? [];
            data_set($metadata, 'redacted_at', now()->toISOString());
            data_set($metadata, 'redacted_by_user_id', Auth::id());
            data_set($metadata, 'redacted_by_agent_id', $agent->id);
            data_set($metadata, 'redaction_count', ($metadata['redaction_count'] ?? 0) + $occurrences);

            $chatMessage->update([
                'message_text'  => $this->mask($chatMessage->message_text, $fragment, $mask),
                'original_text' => $this->mask($chatMessage->original_text, $fragment, $mask),
                'metadata'      => $metadata,
            ]);

            foreach ($chatMessage->translations as $translation) {
                $masked = $this->mask($translation->translated_text, $fragment, $mask);

                if ($masked !== $translation->translated_text) {
                    $translation->update(['translated_text' => $masked]);
                }
            }
        });

        $chatMessage->refresh();

        $this->forgetSummary($chatSession);

        // What was taken out is never written into the record of taking it out.
        StoreChatEvent::run(
            $chatSession,
            ChatEventTypeEnum::REDACT,
            ChatActorTypeEnum::AGENT,
            $agent->id,
            [
                'message_id'  => $chatMessage->id,
                'user_id'     => Auth::id(),
                'occurrences' => $occurrences,
                'characters'  => mb_strlen($fragment),
            ]
        );

        $chatMessage->searchable();

        BroadcastRealtimeChat::dispatch($chatMessage);

        return $chatMessage;
    }

    /**
     * Take a photograph of a card or a passport out of the conversation. The file is
     * removed from every place it is kept — the disk, the media row and the archive
     * database — and the message is left saying that something was removed, so the
     * conversation still reads as it happened.
     *
     * @throws \Throwable
     */
    public function handleAttachment(ChatSession $chatSession, ChatMessage $chatMessage, ChatAgent $agent): ChatMessage
    {
        if ($chatMessage->chat_session_id !== $chatSession->id) {
            throw ValidationException::withMessages([
                'message' => __('Message does not belong to this chat session'),
            ]);
        }

        $media = collect([$chatMessage->attachment])
            ->merge($chatMessage->attachedFiles())
            ->filter()
            ->unique('id');

        if ($media->isEmpty()) {
            throw ValidationException::withMessages([
                'message' => __('This message has nothing attached to it'),
            ]);
        }

        // The archive table is made by the nightly job on its first run, so on an
        // installation where nothing has been archived yet there is simply nothing there.
        $archived = Schema::connection('archive')->hasTable(ArchiveChatMedia::ARCHIVE_TABLE);

        foreach ($media as $file) {
            // The archive copy goes first: losing it while the original survives is the
            // safe way round for a file we have been told to destroy.
            if ($archived) {
                DB::connection('archive')
                    ->table(ArchiveChatMedia::ARCHIVE_TABLE)
                    ->where('media_id', $file->id)
                    ->delete();
            }

            $file->delete();
        }

        $metadata = $chatMessage->metadata ?? [];
        data_set($metadata, 'attachment_redacted_at', now()->toISOString());
        data_set($metadata, 'redacted_by_user_id', Auth::id());
        data_set($metadata, 'redacted_by_agent_id', $agent->id);

        $chatMessage->update([
            'media_id' => null,
            'metadata' => $metadata,
        ]);

        StoreChatEvent::run(
            $chatSession,
            ChatEventTypeEnum::REDACT,
            ChatActorTypeEnum::AGENT,
            $agent->id,
            [
                'message_id' => $chatMessage->id,
                'user_id'    => Auth::id(),
                'files'      => $media->count(),
            ]
        );

        $chatMessage->refresh();

        BroadcastRealtimeChat::dispatch($chatMessage);

        return $chatMessage;
    }

    private function countOccurrences(ChatMessage $chatMessage, string $fragment): int
    {
        $haystacks = [$chatMessage->message_text, $chatMessage->original_text];

        foreach ($chatMessage->translations as $translation) {
            $haystacks[] = $translation->translated_text;
        }

        return collect($haystacks)
            ->filter(fn (?string $text) => $text !== null && $text !== '')
            ->sum(fn (string $text) => mb_substr_count($text, $fragment));
    }

    private function mask(?string $text, string $fragment, string $mask): ?string
    {
        return $text === null ? null : str_replace($fragment, $mask, $text);
    }

    public function inAttachment(string $organisation, ChatSession $chatSession, ChatMessage $chatMessage): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent instanceof ChatAgent) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents can redact messages'),
            ], 403);
        }

        try {
            $chatMessage = $this->handleAttachment($chatSession, $chatMessage, $agent);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Attachment removed'),
            'data'    => (new \App\Http\Resources\CRM\Livechat\ChatMessageResource($chatMessage))->resolve(),
        ]);
    }

    public function asController(string $organisation, ChatSession $chatSession, ChatMessage $chatMessage, ActionRequest $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent instanceof ChatAgent) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents can redact messages'),
            ], 403);
        }

        try {
            $chatMessage = $this->handle($chatSession, $chatMessage, $agent, $request->validated()['fragment']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Message redacted'),
            'data'    => (new \App\Http\Resources\CRM\Livechat\ChatMessageResource($chatMessage))->resolve(),
        ]);
    }

    /**
     * The summary was written from the text as it was, so it may repeat what has just been
     * struck out. It goes at once, and a new one is written from what is left.
     */
    private function forgetSummary(ChatSession $chatSession): void
    {
        $metadata = $chatSession->metadata ?? [];

        if (!isset($metadata['ai_summary'])) {
            return;
        }

        unset($metadata['ai_summary']);
        $chatSession->update(['metadata' => $metadata]);

        SummarizeChatSession::dispatch($chatSession);
    }
}
