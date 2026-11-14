<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\ChatSession\HydrateChatKnowledge;
use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\UpdateShopChatKnowledgeNote;
use App\Actions\Helpers\AI\AskJev;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The answer to a question the AI shopping assistant passed to our team, from the pop-up or written
 * as the note when the task is marked done. The first answer wins; a later one is refused. It is
 * translated from the answering person's language into the conversation's and added to the
 * customer's conversation. Inside the time the customer's page waits it shows there; after it,
 * the customer is also emailed, or customer services passes it on where the shop cannot email.
 * Kept for every customer of the shop unless the person unticks it: a staff note Merlin answers
 * from next time (a discount for one customer must stay unticked).
 *
 * What the person writes is sent to the customer, so two things are checked first. A reply that
 * is not an answer (a question back to us such as "which customer is this?", a note for
 * colleagues, "I do not know") is refused: nothing is sent or kept, and the person is told to
 * write the answer or pass the question on. An answer written about the customer ("they will need
 * to...") is reworded to speak to them, by the light model, keeping every figure, link and email
 * address; when the rewording loses one of them, or fails, the answer goes as written.
 */
class AnswerShoppingAssistantTeamQuestion
{
    use AsAction;
    use WithAIGateway;

    public const float NOT_AN_ANSWER_CONFIDENCE = 0.8;

    /**
     * @return 'answered'|'already_answered'|'not_a_question'|'not_an_answer'
     */
    public function handle(StaffTask $task, User $user, string $answer, bool $keepForEveryone = true): string
    {
        $answer = trim($answer);
        if (data_get($task->data, 'kind') !== StoreShoppingAssistantTeamQuestion::TASK_KIND || $answer === '') {
            return 'not_a_question';
        }

        $question = StoreShoppingAssistantTeamQuestion::questionText($task);
        if (!data_get($task->data, 'answered_at') && $this->isNotAnAnswer($question, $answer)) {
            return 'not_an_answer';
        }

        $answeredLate = null;
        $won          = DB::transaction(function () use ($task, $user, $answer, $keepForEveryone, &$answeredLate) {
            $locked = StaffTask::whereKey($task->id)->lockForUpdate()->first();
            if (data_get($locked->data, 'answered_at')) {
                return false;
            }
            $answeredLate = !StoreShoppingAssistantTeamQuestion::isAwaited($locked);
            $locked->update(['data' => [...$locked->data, 'answer' => $answer, 'answered_at' => now()->toIso8601String(), 'answered_by' => $user->id, 'kept_for_everyone' => $keepForEveryone]]);

            return true;
        });

        if (!$won) {
            return 'already_answered';
        }

        $task->refresh();
        $assistant    = GetAikuAssistant::run($task->group_id);
        $conversation = ShoppingAssistantConversation::with('customer.shop', 'website')->find(data_get($task->data, 'conversation_id'));
        $followUp     = FollowUpShoppingAssistantRestockQuestion::make();

        if ($conversation?->customer && !$conversation->wiped_at) {
            $translated = $followUp->translate($this->forCustomer($question, $answer), $conversation->language_code, $user->language_id ? Language::find($user->language_id) : null);
            $message    = __('Our team answered your question:', [], $conversation->language_code)."\n\n".$translated;

            $conversation->messages()->create([
                'role'  => ShoppingAssistantMessageRoleEnum::ASSISTANT,
                'body'  => $message,
                'model' => 'team',
                'flags' => ['team_answer'],
            ]);
            $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

            if ($answeredLate) {
                [, $log] = $followUp->deliver($conversation, $message, $translated, $answer, __('Our team answered a question this customer asked the AI shopping assistant (:reference). The shop cannot email it automatically, so please send it to the customer.', ['reference' => $task->reference]), $task, $assistant);
                if ($task->conversation) {
                    SendStaffMessage::run($task->conversation, $assistant, ['body' => $log]);
                }
            }
        }

        $shop = Shop::find(data_get($task->data, 'shop_id'));
        if ($keepForEveryone && $shop) {
            UpdateShopChatKnowledgeNote::make()->handle($shop, null, ['title' => mb_substr(str_replace("\n", ' ', $question), 0, 200), 'body' => mb_substr($answer, 0, 2000)], $user, $this->needsSecondYes($task, $user) ? 'proposed' : 'active');
            dispatch(fn () => HydrateChatKnowledge::make()->embedMissing($shop));
        }

        if ($task->status->isOpen()) {
            UpdateStaffTask::run($task, $user, ['status' => StaffTaskStatusEnum::DONE->value, 'note' => $answer]);
        }

        return 'answered';
    }

    /**
     * While new answers are reviewed, a kept answer waits for another guru of the shop; with no
     * other guru to say yes, it is used at once rather than never.
     */
    private function needsSecondYes(StaffTask $task, User $user): bool
    {
        return config('services.shopping_assistant.review_new_answers')
            && collect(StoreShoppingAssistantTeamQuestion::gurusOfTask($task))->contains(fn (User $guru) => $guru->id !== $user->id);
    }

    public function authorize(ActionRequest $request): bool
    {
        return StoreShoppingAssistantTeamQuestion::canAnswer($request->route('staffTask'), $request->user());
    }

    public function rules(): array
    {
        return [
            'answer'             => ['required', 'string', 'max:2000'],
            'keep_for_everyone'  => ['sometimes', 'boolean'],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): JsonResponse
    {
        $result = $this->handle($staffTask, $request->user(), $request->validated('answer'), (bool) $request->validated('keep_for_everyone', true));

        if ($result === 'not_an_answer') {
            return response()->json(['result' => $result, 'message' => self::notAnAnswerMessage()], 422);
        }

        return response()->json(['result' => $result], $result === 'answered' ? 200 : 409);
    }

    public static function notAnAnswerMessage(): string
    {
        return __('Not sent: this reads as a question or a note for us, not an answer. What you write here goes to the customer as it is, so write the answer for them, or pass the question to a colleague. Who the customer is and what they asked is under Customer & chat details.');
    }

    /**
     * Jev's verdict on the reply; no verdict (no key, Jev down) lets the answer through.
     */
    private function isNotAnAnswer(string $question, string $answer): bool
    {
        $probability = AskJev::make()->noul(
            ['customer_question' => $question, 'staff_reply' => $answer],
            'A customer of our shop asked this question and a member of our staff wrote this reply in a box whose text is sent to the customer, as written, as our answer. Is the reply something that must NOT be sent to the customer as the answer?',
            'It is not an answer for the customer: it asks us or a colleague for something (who the customer is, a link to their account, more details), it is a note for colleagues, or it only says the writer does not know.',
            'It answers the customer, fully or in part, or tells the customer what to do next: also when it says we cannot do what they ask, asks the customer for a detail, or tells them whom to contact.'
        );

        return $probability !== null && $probability >= self::NOT_AN_ANSWER_CONFIDENCE;
    }

    /**
     * The answer as a reply to the customer, in the language it was written in.
     */
    private function forCustomer(string $question, string $answer): string
    {
        $model = config('services.shopping_assistant.light_model');
        if (!$model || !config('services.openrouter.api_key')) {
            return $answer;
        }

        try {
            $reworded = trim((string) $this->aiRequest()
                ->connectTimeout(10)
                ->timeout(30)
                ->post('chat/completions', [
                    'model'      => $this->aiModel($model),
                    'max_tokens' => 1500,
                    'messages'   => [
                        ['role' => 'system', 'content' => 'A member of our staff wrote an answer to a question a customer asked on our trade website. Rewrite it as our reply to that customer: speak to the customer directly (you), never about them (the customer, they), and leave out anything said to colleagues. Keep the language it is written in. Keep every fact, figure, name, link and email address exactly as written, and add nothing. If it already reads as a reply to the customer, return it unchanged. Return only the reply text.'],
                        ['role' => 'user', 'content' => "The customer's question:\n$question\n\nThe staff answer:\n$answer"],
                    ],
                ])
                ->throw()
                ->json('choices.0.message.content'));
        } catch (Throwable $exception) {
            Log::warning('Team answer rewording failed: '.$exception->getMessage());

            return $answer;
        }

        return $reworded !== '' && mb_strlen($reworded) <= mb_strlen($answer) * 2 + 200 && !array_diff($this->exactParts($answer), $this->exactParts($reworded)) ? $reworded : $answer;
    }

    /**
     * What a rewording may never change: figures, links and email addresses.
     *
     * @return array<int, string>
     */
    private function exactParts(string $text): array
    {
        preg_match_all('/https?:\/\/[^\s<>"\']+|[\w.+-]+@[\w-]+(?:\.[\w-]+)+|\d+(?:[.,]\d+)*/u', $text, $parts);

        return array_values(array_unique(array_map(fn (string $part) => rtrim($part, '.,;:!?)'), $parts[0])));
    }
}
