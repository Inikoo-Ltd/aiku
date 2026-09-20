<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Http\Resources\CRM\Livechat\ChatTimelineEventResource;
use App\Models\Chat\ChatAgent;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Collection;

class GetChatMessages
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function rules(): array
    {
        return [
            'is_read' => ['sometimes', 'boolean'],
            'translation_language_id' => ['sometimes', 'integer', 'exists:languages,id'],
            'sender_type' => [
                'sometimes',
                Rule::in(array_column(ChatSenderTypeEnum::cases(), 'value')),
            ],
            'request_from' => [
                'sometimes',
                'string',
                Rule::in([
                    ChatSenderTypeEnum::AGENT->value,
                    ChatSenderTypeEnum::USER->value,
                    ChatSenderTypeEnum::GUEST->value,
                ])
            ],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'date'],
        ];
    }


    public function asController(ChatSession $chatSession, ActionRequest $request): array
    {
        $validated = $request->validated();

        $user = $request->user();
        $forStaff = $user instanceof User
            && $chatSession->shop instanceof Shop
            && $this->userCanViewChatOnShop($user, $chatSession->shop);

        if (array_key_exists('request_from', $validated)) {
            $requestFrom = $validated['request_from'] ?? ChatSenderTypeEnum::GUEST->value;
            $readerType = ChatSenderTypeEnum::tryFrom($requestFrom) ?? ChatSenderTypeEnum::GUEST;
            MarkChatMessagesAsRead::run($chatSession, $readerType);
        }

        $messages = $this->handle($chatSession, $validated, $forStaff);

        $nextCursor = null;

        if ($messages->isNotEmpty()) {
            $oldest = $messages->first();
            $nextCursor = $oldest->created_at->toISOString();
        }

        $hasMore = $this->hasMore($chatSession, $nextCursor);

        return [
            'chatSession' => $chatSession,
            'messages' => $messages,
            'events' => $this->timelineEvents($chatSession, $messages, $validated),
            'pagination' => [
                'has_more'    => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'count'       => $messages->count(),
                'limit'       => $validated['limit'] ?? 20,
            ]
        ];
    }



    /**
     * A message that was taken back stays in the list for both sides, so the customer is
     * told that something was withdrawn rather than watching it silently disappear. Only
     * staff get to keep reading it: for anybody else the words are stripped here, leaving
     * the marker and the reason. This endpoint is public, so the request cannot ask to be
     * treated as staff.
     */
    public function handle(ChatSession $chatSession, array $filters, bool $forStaff = false)
    {
        $query = $chatSession->messages()
            ->withTrashed()
            ->with([
                'media',
                'translations' => function ($query) use ($filters) {
                    $query->with('targetLanguage');
                    if (!empty($filters['translation_language_id'])) {
                        $query->where('target_language_id', $filters['translation_language_id']);
                    }
                },
                'originalLanguage',
                'attachment',
                'reactions',
                'chatSession.assignments.chatAgent.user'
            ])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['cursor'])) {
            $query->where('created_at', '<', $filters['cursor']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (!empty($filters['sender_type'])) {
            $query->where('sender_type', $filters['sender_type']);
        }

        $limit = $filters['limit'] ?? 20;

        $messages = $query->limit($limit)->get()->sortBy('created_at')->values();

        $this->attachAgentSenderNames($messages);

        if (!$forStaff) {
            $messages->each(fn ($message) => $this->stripRetracted($message));
        }

        return $messages;
    }

    private function stripRetracted(\App\Models\Chat\ChatMessage $message): void
    {
        if (!$message->trashed()) {
            return;
        }

        $message->setAttribute('message_text', null);
        $message->setAttribute('original_text', null);
        $message->setRelation('translations', collect());
    }

    private function attachAgentSenderNames(Collection $messages): void
    {
        $agentIds = $messages
            ->filter(fn ($message) => $message->sender_type === ChatSenderTypeEnum::AGENT && $message->sender_id)
            ->pluck('sender_id')
            ->unique()
            ->all();

        if (empty($agentIds)) {
            return;
        }

        $names = ChatAgent::query()
            ->whereIn('id', $agentIds)
            ->with('user:id,contact_name,username')
            ->get()
            ->mapWithKeys(fn (ChatAgent $chatAgent) => [
                $chatAgent->id => $chatAgent->user?->contact_name ?? $chatAgent->user?->username,
            ]);

        foreach ($messages as $message) {
            if ($message->sender_type === ChatSenderTypeEnum::AGENT && $message->sender_id) {
                $message->setAttribute('sender_name', $names[$message->sender_id] ?? null);
            }
        }
    }


    public function jsonResponse($result): JsonResponse
    {
        $fullName = $result['chatSession']->assignments->last()?->chatAgent?->user?->contact_name
            ?? $result['chatSession']->assignments->last()?->chatAgent?->user?->username
            ?? null;

        $firstName = $fullName ? explode(' ', trim($fullName))[0] : null;
        return response()->json([
            'success' => true,
            'message' => 'Chat messages retrieved successfully',
            'data' => [
                'session_ulid'   => $result['chatSession']->ulid,
                'session_status' => $result['chatSession']->status->value,
                'assigned_agent' => $firstName,
                'rating'         => $result['chatSession']->rating,
                'messages'       => ChatMessageResource::collection($result['messages']),
                'events'         => ChatTimelineEventResource::collection($result['events']),
                'pagination'     => $result['pagination'],
            ]
        ]);
    }

    /**
     * Events are windowed to the messages on this page rather than paginated on their
     * own, so loading older messages brings their events along without a second cursor.
     * Only the agent view gets them: the visitor widget has no business seeing that its
     * chat was flagged as spam or moved between agents.
     */
    protected function timelineEvents(ChatSession $chatSession, Collection $messages, array $filters): Collection
    {
        if (($filters['request_from'] ?? null) !== ChatSenderTypeEnum::AGENT->value || $messages->isEmpty()) {
            return collect();
        }

        $query = $chatSession->chatEvents()
            ->whereIn('event_type', ChatEventTypeEnum::timelineTypes())
            ->where('created_at', '>=', $messages->first()->created_at);

        if (!empty($filters['cursor'])) {
            $query->where('created_at', '<', $filters['cursor']);
        }

        return $query->orderBy('created_at')->get();
    }

    private function hasMore(ChatSession $chatSession, ?string $cursor): bool
    {
        if (!$cursor) {
            return false;
        }

        return $chatSession->messages()
            ->where('created_at', '<', $cursor)
            ->exists();
    }
}
