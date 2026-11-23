<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatAuraQuestion;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * "Redraft with this" in the Ask Aura window (INI-519): Aura rewrites the reply in the box using one
 * of her answers, in the language the customer wrote in. When the box holds her waiting draft, the
 * rewrite replaces that draft's text, so the reply staff send from it is still measured as hers;
 * the text it replaced is kept in facts.redrafts. Otherwise the rewrite goes back to the box as the
 * agent's own reply. Either way the agent reads it before sending.
 */
class RedraftChatWithAura
{
    use AsAction;
    use WithAIGateway;
    use WithChatAgentAuthorisation;

    public const string MODEL = 'anthropic/claude-sonnet-5.5';

    public const int MAX_CHARACTERS = 8000;

    /**
     * @return array{text: string, draft_id: int|null}
     * @throws ValidationException
     */
    public function handle(ChatAuraQuestion $question, string $reply, ?ChatAiDraft $draft): array
    {
        $chat = $question->chat();

        if (!$this->usesOpenRouter()) {
            throw ValidationException::withMessages(['redraft' => __('The AI gateway is not set up. Add OPENROUTER_API_KEY to the environment.')]);
        }

        $language = $this->replyLanguage($chat);

        try {
            $response = $this->aiRequest()
                ->timeout(45)
                ->post('chat/completions', [
                    'model'           => $this->aiModel(self::MODEL),
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => 'Write the reply in '.($language?->name ?? 'the language the customer wrote in').".\n\n<agent_question>\n{$question->question}\n</agent_question>\n\n<your_answer>\n{$question->answer}\n</your_answer>\n\n<reply>\n".($reply !== '' ? $reply : (string) $draft?->text)."\n</reply>"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens'      => 6000,
                ])
                ->throw();
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['redraft' => __('The AI gateway did not answer: :error', ['error' => Str::limit($e->getMessage(), 200)])]);
        }

        $content = (string) $response->json('choices.0.message.content');
        $answer  = preg_match('/\{.*\}/s', $content, $match) ? json_decode($match[0], true) : null;
        $text    = is_array($answer) && is_string($answer['text'] ?? null) ? trim($answer['text']) : '';

        if ($text === '' || str_contains($text, '[[')) {
            throw ValidationException::withMessages(['redraft' => __('Aura could not rewrite the reply. Try again.')]);
        }

        if ($draft) {
            $draft->update([
                'text'               => $text,
                'staff_translations' => null,
                'facts'              => [...($draft->facts ?? []), 'redrafts' => [...data_get($draft->facts, 'redrafts', []), ['from' => $draft->text, 'aura_question_id' => $question->id, 'at' => now()->toIso8601String()]]],
            ]);
        }

        $question->update(['redrafted_at' => now()]);

        return ['text' => $text, 'draft_id' => $draft?->id];
    }

    public function asController(ChatAuraQuestion $chatAuraQuestion, Request $request): JsonResponse
    {
        $user = $request->user();
        $chat = $chatAuraQuestion->chat();

        abort_unless($user instanceof User && $chatAuraQuestion->user_id === $user->id && $chatAuraQuestion->answered_at && !in_array('no_answer', $chatAuraQuestion->flags, true) && $chat?->shop && $this->userCanViewChatOnShop($user, $chat->shop), 403);

        $validated = $request->validate([
            'text'     => ['present', 'nullable', 'string', 'max:'.self::MAX_CHARACTERS],
            'draft_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $draft = null;
        if (!empty($validated['draft_id'])) {
            $draft = DraftChatReply::pendingDraft($chat);
            if ($draft?->id !== (int) $validated['draft_id'] || !$this->getAuthorisedChatAgent($chat)) {
                throw ValidationException::withMessages(['redraft' => __('This draft is no longer waiting')]);
            }
        }

        $reply = trim((string) ($validated['text'] ?? ''));
        if ($reply === '' && !$draft) {
            throw ValidationException::withMessages(['redraft' => __('There is no reply to rewrite yet.')]);
        }

        return response()->json(['data' => $this->handle($chatAuraQuestion, $reply, $draft)]);
    }

    private function replyLanguage(ChatSession|MetaChatSession $chat): ?Language
    {
        $trigger = $chat->messages()->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])->latest('id')->first();

        return $trigger
            ? DraftChatReply::replyLanguage($chat, $trigger, ClassifyChatTurn::customerWrote($chat))
            : ($chat->language ?? $chat->shop?->language);
    }

    /**
     * Kept the same for every call so the gateway can cache it.
     */
    private function instructions(): string
    {
        return "You are Aura, the customer service assistant of a giftware wholesaler and retailer. A customer service agent asked you a question about the chat they are answering, you answered, and now they want the reply to the customer rewritten using your answer.\n\n"
            ."Rewrite the reply so it uses what your answer says that the customer needs. Keep everything else the reply already says, its greeting, sign-off, tone and length, unless your answer shows it is wrong. Write it to the customer, in the language you are told.\n\n"
            ."Never add an order number, amount, date, stock level, product code, tracking number or promise that is not in the reply or in your answer. Leave out anything internal: staff names, how Aiku works, queues, margins, other customers. Never leave [[gaps]] or placeholders.\n\n"
            ."Keep the line breaks and the formatting marks the reply uses; add no headings and no emoji.\n\n"
            .'Answer only with a JSON object: {"text": "<the rewritten reply>"}.';
    }
}
