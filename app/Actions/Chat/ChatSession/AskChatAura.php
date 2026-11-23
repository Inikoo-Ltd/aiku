<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatAuraQuestion;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The "Ask Aura" window behind her tag in the reply box (INI-519): an agent who can open the chat
 * asks her about it, and sees only their own questions on it. The answer comes from
 * AnswerChatAuraQuestion on the queue; the window polls the list until it is there.
 */
class AskChatAura
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const int MAX_QUESTION_CHARACTERS = 2000;

    public const int QUESTIONS_PER_TEN_MINUTES = 30;

    public const int LISTED = 30;

    public const int GIVEN_UP_MINUTES = 5;

    public function handle(ChatSession|MetaChatSession $chatSession, User $user, string $question): ChatAuraQuestion
    {
        $auraQuestion = ChatAuraQuestion::create([
            'group_id'             => $chatSession->shop->group_id,
            'shop_id'              => $chatSession->shop_id,
            'chat_session_id'      => $chatSession instanceof ChatSession ? $chatSession->id : null,
            'meta_chat_session_id' => $chatSession instanceof MetaChatSession ? $chatSession->id : null,
            'user_id'              => $user->id,
            'question'             => $question,
        ]);

        AnswerChatAuraQuestion::dispatch($auraQuestion);

        return $auraQuestion;
    }

    public function asController(ChatSession $chatSession, Request $request): JsonResponse
    {
        return $this->store($chatSession, $request);
    }

    public function index(ChatSession $chatSession, Request $request): JsonResponse
    {
        return $this->list($chatSession, $request->user());
    }

    private function store(ChatSession|MetaChatSession $chatSession, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $chatSession->shop && $this->userCanViewChatOnShop($user, $chatSession->shop), 403);

        $question = trim((string) $request->validate([
            'question' => ['required', 'string', 'max:'.self::MAX_QUESTION_CHARACTERS],
        ])['question']);

        $key = 'chat-aura-question:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::QUESTIONS_PER_TEN_MINUTES)) {
            throw ValidationException::withMessages(['question' => __('Too many questions in a short time. Try again in a few minutes.')]);
        }
        RateLimiter::hit($key, 600);

        $this->handle($chatSession, $user, $question);

        return $this->list($chatSession, $user);
    }

    private function list(ChatSession|MetaChatSession $chatSession, mixed $user): JsonResponse
    {
        abort_unless($user instanceof User && $chatSession->shop && $this->userCanViewChatOnShop($user, $chatSession->shop), 403);

        $questions = ChatAuraQuestion::where('user_id', $user->id)
            ->where($chatSession instanceof ChatSession ? 'chat_session_id' : 'meta_chat_session_id', $chatSession->id)
            ->latest('id')
            ->limit(self::LISTED)
            ->get()
            ->reverse()
            ->map(fn (ChatAuraQuestion $question) => [
                'id'          => $question->id,
                'question'    => $question->question,
                'answer'      => $question->answer ?? ($question->created_at?->lt(now()->subMinutes(self::GIVEN_UP_MINUTES)) ? __('Aura did not answer this one. Please ask again.') : null),
                'answered'    => $question->answered_at !== null || $question->created_at?->lt(now()->subMinutes(self::GIVEN_UP_MINUTES)),
                'failed'      => in_array('no_answer', $question->flags, true) || ($question->answered_at === null && $question->created_at?->lt(now()->subMinutes(self::GIVEN_UP_MINUTES))),
                'redrafted'   => $question->redrafted_at !== null,
                'asked_at'    => $question->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $questions]);
    }
}
