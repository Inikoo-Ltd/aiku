<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 19:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatReplyDraft;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4523: a reply an agent has started is kept on the server, one per conversation and
 * agent, so a colleague who opens the conversation sees it (and can carry it on) instead of
 * starting again from Aiku's draft, and the inbox row says a draft is waiting. Emptying the box
 * or sending the reply removes it. The list is told only when a draft appears or goes, never on
 * each save.
 */
class SaveChatReplyDraft
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const MAX_LENGTH = 100_000;

    public function handle(ChatSession $chatSession, ChatAgent $agent, string $text): ?ChatReplyDraft
    {
        if (trim($text) === '') {
            $this->forget($chatSession, $agent);

            return null;
        }

        $draft = ChatReplyDraft::updateOrCreate(
            ['chat_session_id' => $chatSession->id, 'chat_agent_id' => $agent->id],
            ['text' => $text]
        );

        if ($draft->wasRecentlyCreated) {
            BroadcastChatListEvent::dispatch(null, $chatSession);
        }

        return $draft;
    }

    public function forget(ChatSession $chatSession, ChatAgent|int $agent): void
    {
        $deleted = ChatReplyDraft::where('chat_session_id', $chatSession->id)
            ->where('chat_agent_id', $agent instanceof ChatAgent ? $agent->id : $agent)
            ->delete();

        if ($deleted) {
            BroadcastChatListEvent::dispatch(null, $chatSession);
        }
    }

    /**
     * The agent's own draft, and the most recent one a colleague left.
     *
     * @return array{mine: array{text: string, updated_at: mixed}|null, colleague: array{id: int, name: string, text: string, updated_at: mixed}|null}
     */
    public function draftsFor(ChatSession $chatSession, ChatAgent $agent): array
    {
        $drafts = $chatSession->replyDrafts()->with('chatAgent.user')->latest('updated_at')->get();

        $mine = $drafts->firstWhere('chat_agent_id', $agent->id);
        // ponytail: only the latest colleague's draft is offered; list them all if two people ever leave one on the same conversation
        $colleague = $drafts->first(fn (ChatReplyDraft $draft) => $draft->chat_agent_id !== $agent->id);

        return [
            'mine'      => $mine ? ['text' => $mine->text, 'updated_at' => $mine->updated_at] : null,
            'colleague' => $colleague ? [
                'id'         => $colleague->id,
                'agent_id'   => $colleague->chat_agent_id,
                'name'       => $colleague->writerName(),
                'text'       => $colleague->text,
                'updated_at' => $colleague->updated_at,
            ] : null,
        ];
    }

    /**
     * Carrying on a colleague's draft makes it this agent's: theirs goes, so the reply is not
     * finished twice, and the agent's own empty-or-older draft is replaced.
     */
    public function takeOverDraft(ChatSession $chatSession, ChatAgent $agent, ChatReplyDraft $draft): ChatReplyDraft
    {
        ChatReplyDraft::where('chat_session_id', $chatSession->id)
            ->where('chat_agent_id', $agent->id)
            ->whereKeyNot($draft->id)
            ->delete();

        $draft->update(['chat_agent_id' => $agent->id]);

        BroadcastChatListEvent::dispatch(null, $chatSession);

        return $draft;
    }

    public function rules(): array
    {
        return [
            'text'    => ['present', 'nullable', 'string', 'max:'.self::MAX_LENGTH],
            'existed' => ['sometimes', 'boolean'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = $this->agentOrAbort($chatSession);
        $text  = (string) $request->validated('text');

        // The tab was carrying on a draft that is gone: a colleague took it over, or it was sent
        // from another tab. Bringing it back would get the customer the same reply twice.
        if ($request->boolean('existed') && trim($text) !== ''
            && !$chatSession->replyDrafts()->where('chat_agent_id', $agent->id)->exists()) {
            $holder = $chatSession->replyDrafts()->with('chatAgent.user')->latest('updated_at')->first();

            return response()->json(['saved' => false, 'name' => $holder?->writerName()], 409);
        }

        return response()->json(['saved' => (bool) $this->handle($chatSession, $agent, $text)]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function show(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        return response()->json($this->draftsFor($chatSession, $this->agentOrAbort($chatSession)));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function takeOver(?string $organisation, ChatSession $chatSession, Request $request): JsonResponse
    {
        $agent = $this->agentOrAbort($chatSession);

        $draft = DB::transaction(function () use ($chatSession, $agent, $request) {
            // Only the draft as it was shown: if someone else carried it on meanwhile, nobody gets it twice.
            $draft = $chatSession->replyDrafts()
                ->whereKey($request->integer('draft_id'))
                ->where('chat_agent_id', $request->integer('agent_id'))
                ->where('chat_agent_id', '!=', $agent->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->takeOverDraft($chatSession, $agent, $draft);
        });

        return response()->json(['text' => $draft->text]);
    }

    private function agentOrAbort(ChatSession $chatSession): ChatAgent
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);
        abort_unless($agent instanceof ChatAgent, 403);

        return $agent;
    }
}
