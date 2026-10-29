<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 23:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One WhatsApp contact with two conversations in the same shop: a thread that was binned and a
 * new one started beside it when they wrote again. Merging moves everything of the other thread
 * (messages, calls, notes, assignments, AI drafts, tickets and tasks) into the conversation the
 * agent is on, and bins the emptied thread with a note saying where it went, so it is never
 * found again by an incoming message.
 *
 * Only the same contact merges: the same number or WhatsApp user, in the same shop. Each shop
 * writes from its own WhatsApp number and keeps its own customer records, so a contact writing
 * to two shops has two real conversations; those show side by side in the conversation history.
 */
class MergeMetaChatSessions
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const string MERGED_INTO_KEY = 'merged_into';

    private const array SESSION_TABLES = [
        'meta_chat_messages',
        'meta_chat_events',
        'meta_chat_assignments',
        'meta_chat_calls',
        'meta_chat_message_reactions',
        'meta_tracking_events',
        'chat_ai_drafts',
        'chat_ai_outcomes',
        'chat_turn_readings',
    ];

    private const array MORPHS = [
        ['tickets', 'source'],
        ['tickets', 'model'],
        ['staff_tasks', 'model'],
        ['staff_conversations', 'context'],
        ['whatsapp_recipients', 'recipient'],
        ['whatsapp_subscribers', 'parent'],
    ];

    /**
     * @return Collection<int, MetaChatSession>
     */
    public static function duplicatesOf(MetaChatSession $metaChatSession): Collection
    {
        $digits = preg_replace('/\D/', '', (string) $metaChatSession->phone_number);

        if ($digits === '' && !$metaChatSession->whatsapp_user_id) {
            return collect();
        }

        return MetaChatSession::withTrashed()
            ->where('meta_channel_id', $metaChatSession->meta_channel_id)
            ->where('shop_id', $metaChatSession->shop_id)
            ->where('id', '!=', $metaChatSession->id)
            ->whereNull('metadata->'.self::MERGED_INTO_KEY)
            ->where(function ($query) use ($digits, $metaChatSession) {
                if ($digits !== '') {
                    $query->whereIn('phone_number', ['+'.$digits, $digits]);
                }

                if ($metaChatSession->whatsapp_user_id) {
                    $query->orWhere('whatsapp_user_id', $metaChatSession->whatsapp_user_id);
                }
            })
            ->withCount('messages')
            ->orderBy('id')
            ->get();
    }

    /**
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, MetaChatSession $duplicate, ChatAgent $agent): MetaChatSession
    {
        if ($metaChatSession->trashed()) {
            throw ValidationException::withMessages(['duplicate' => __('Restore this conversation before merging another into it')]);
        }

        if (!self::duplicatesOf($metaChatSession)->contains('id', $duplicate->id)) {
            throw ValidationException::withMessages(['duplicate' => __('Only a conversation with the same WhatsApp contact in the same shop can be merged')]);
        }

        $merged = DB::transaction(function () use ($metaChatSession, $duplicate, $agent) {
            $messagesCount = $duplicate->messages()->count();

            // One pending draft and one active assignment per conversation: the conversation
            // kept answers for both, so the other thread's are settled before they move.
            ChatAiDraft::where('meta_chat_session_id', $duplicate->id)
                ->where('status', ChatAiDraftStatusEnum::PENDING->value)
                ->update(['status' => ChatAiDraftStatusEnum::SUPERSEDED->value]);

            if ($metaChatSession->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->exists()) {
                $duplicate->assignments()
                    ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                    ->update(['status' => ChatAssignmentStatusEnum::RESOLVED->value]);
            }

            foreach (self::SESSION_TABLES as $table) {
                DB::table($table)->where('meta_chat_session_id', $duplicate->id)->update(['meta_chat_session_id' => $metaChatSession->id]);
            }

            foreach (self::MORPHS as [$table, $morph]) {
                DB::table($table)
                    ->where("{$morph}_type", 'MetaChatSession')
                    ->where("{$morph}_id", $duplicate->id)
                    ->update(["{$morph}_id" => $metaChatSession->id]);
            }

            $metaChatSession->update(array_filter([
                'customer_id'             => $metaChatSession->customer_id ?? $duplicate->customer_id,
                'whatsapp_user_id'        => $metaChatSession->whatsapp_user_id ?? $duplicate->whatsapp_user_id,
                'phone_number'            => $metaChatSession->phone_number ?? $duplicate->phone_number,
                'last_visitor_message_at' => collect([$metaChatSession->last_visitor_message_at, $duplicate->last_visitor_message_at])->filter()->max(),
                'last_agent_message_at'   => collect([$metaChatSession->last_agent_message_at, $duplicate->last_agent_message_at])->filter()->max(),
            ]));

            $duplicate->update([
                'metadata'       => array_merge($duplicate->metadata ?? [], [self::MERGED_INTO_KEY => $metaChatSession->ulid]),
                'delete_comment' => __('Merged into conversation :ulid', ['ulid' => $metaChatSession->ulid]),
            ]);

            if (!$duplicate->trashed()) {
                $duplicate->delete();
            }

            StoreMetaChatEvent::make()->handle($metaChatSession, ChatEventTypeEnum::NOTE, ChatActorTypeEnum::AGENT, $agent->id, [
                'action_type'    => 'conversation_merged',
                'merged_ulid'    => $duplicate->ulid,
                'messages_count' => $messagesCount,
                'agent_name'     => $agent->user?->contact_name,
            ]);

            return $metaChatSession->fresh();
        });

        // The customer writing again into the other thread is what usually leaves two: their
        // message must not end up unanswered in a closed conversation.
        if ($merged->status === ChatSessionStatusEnum::CLOSED && $duplicate->status !== ChatSessionStatusEnum::CLOSED) {
            $merged = ReopenMetaChatSession::make()->reopenToWaiting($merged, ChatActorTypeEnum::AGENT, $agent->id);
        }

        BroadcastMetaChatListEvent::dispatch(null, $merged);
        BroadcastMetaChatListEvent::dispatch(null, $duplicate);

        return $merged;
    }

    public function duplicates(MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        $user = $request->user();

        if (!$user instanceof User || !$metaChatSession->shop || !$this->userCanViewChatOnShop($user, $metaChatSession->shop)) {
            abort(403);
        }

        return self::duplicatesOf($metaChatSession)->map(fn (MetaChatSession $duplicate) => [
            'ulid'            => $duplicate->ulid,
            'started_at'      => $duplicate->created_at,
            'last_message_at' => $duplicate->last_visitor_message_at ?? $duplicate->last_agent_message_at ?? $duplicate->created_at,
            'messages_count'  => $duplicate->messages_count,
            'is_binned'       => $duplicate->trashed(),
            'phone_number'    => $duplicate->phone_number,
        ])->values()->all();
    }

    public function rules(): array
    {
        return [
            'duplicate_ulid' => ['required', 'string'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($metaChatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only authenticated agents can merge conversations')], 403);
        }

        $duplicate = MetaChatSession::withTrashed()->where('ulid', $request->validated()['duplicate_ulid'])->first();

        if (!$duplicate) {
            return response()->json(['success' => false, 'message' => __('That conversation no longer exists')], 422);
        }

        if (!$this->userCanDisposeOfChat($request->user(), $duplicate)) {
            return response()->json(['success' => false, 'message' => $this->chatHeldByAnotherAgentMessage($duplicate)], 403);
        }

        try {
            $merged = $this->handle($metaChatSession, $duplicate, $agent);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['success' => true, 'data' => ['session_ulid' => $merged->ulid]]);
    }
}
