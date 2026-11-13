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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The answer to a question the AI shopping assistant passed to our team, from the pop-up or written
 * as the note when the task is marked done. The first answer wins; a later one is refused. It is
 * translated from the answering person's language into the conversation's and added to the
 * customer's conversation. Inside the time the customer's page waits it shows there; after it,
 * the customer is also emailed, or customer services passes it on where the shop cannot email.
 * Kept for every customer of the shop unless the person unticks it: a staff note Merlin answers
 * from next time (a discount for one customer must stay unticked).
 */
class AnswerShoppingAssistantTeamQuestion
{
    use AsAction;

    /**
     * @return 'answered'|'already_answered'|'not_a_question'
     */
    public function handle(StaffTask $task, User $user, string $answer, bool $keepForEveryone = true): string
    {
        $answer = trim($answer);
        if (data_get($task->data, 'kind') !== StoreShoppingAssistantTeamQuestion::TASK_KIND || $answer === '') {
            return 'not_a_question';
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
            $translated = $followUp->translate($answer, $conversation->language_code, $user->language_id ? Language::find($user->language_id) : null);
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
            UpdateShopChatKnowledgeNote::make()->handle($shop, null, ['title' => mb_substr((string) data_get($task->data, 'question'), 0, 200), 'body' => mb_substr($answer, 0, 2000)], $user);
            dispatch(fn () => HydrateChatKnowledge::make()->embedMissing($shop));
        }

        if ($task->status->isOpen()) {
            UpdateStaffTask::run($task, $user, ['status' => StaffTaskStatusEnum::DONE->value, 'note' => $answer]);
        }

        return 'answered';
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

        return response()->json(['result' => $result], $result === 'answered' ? 200 : 409);
    }
}
