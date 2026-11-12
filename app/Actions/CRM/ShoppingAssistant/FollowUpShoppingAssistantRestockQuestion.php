<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Comms\Email\SendChatNotificationToCustomer;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A restock question marked done: its answer goes to the customer. The answer is the note written
 * when marking the task done, else the last message the purchasing team wrote in the task's thread.
 * It is translated from English into the shop's language and added to the customer's assistant
 * conversation, then emailed with the shop's "Chat notification to customer" email and a link that
 * opens that conversation. Where that email is not switched on (12 Nov 2026: UK, Aroma, aeu, acar,
 * ace, awad), customer services gets a task to pass the answer on. With no answer written, nothing
 * is sent and the thread says so. An answer is sent once.
 */
class FollowUpShoppingAssistantRestockQuestion
{
    use AsAction;

    public string $jobQueue = 'default';

    public function handle(StaffTask $task, User $actor, ?string $note = null): ?string
    {
        if (data_get($task->data, 'kind') !== StoreShoppingAssistantRestockQuestion::TASK_KIND || $task->status !== StaffTaskStatusEnum::DONE || data_get($task->data, 'answer_sent_at')) {
            return null;
        }

        $assistant    = GetAikuAssistant::run($task->group_id);
        $conversation = ShoppingAssistantConversation::with('customer.shop', 'website')->find(data_get($task->data, 'conversation_id'));
        $answer       = trim((string) $note) ?: $this->lastAnswerInThread($task, $assistant);

        if (!$conversation?->customer || $answer === '') {
            if ($task->conversation) {
                SendStaffMessage::run($task->conversation, $assistant, ['body' => __('Nothing was sent to the customer: no answer was written. Reopen this task and write the answer in the note when you mark it done.')]);
            }

            return null;
        }

        $customer   = $conversation->customer;
        $locale     = $conversation->language_code;
        $translated = $this->translate($answer, $locale);
        $message    = __('Our purchasing team answered your question:', [], $locale)."\n\n".$translated;

        $conversation->messages()->create([
            'role'  => ShoppingAssistantMessageRoleEnum::ASSISTANT,
            'body'  => $message,
            'model' => 'purchasing-team',
            'flags' => ['purchasing_answer'],
        ]);
        $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

        $emailIsOn = $customer->shop->outboxes()
            ->where('code', OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER->value)
            ->where('state', OutboxStateEnum::ACTIVE->value)
            ->exists();

        if ($emailIsOn && $customer->email) {
            SendChatNotificationToCustomer::dispatch($customer, [
                'chat_link'    => $conversation->website ? $conversation->website->getUrl().'/assistant?conversation='.$conversation->ulid : null,
                'chat_message' => $message,
            ]);
            $channel = 'email';
            $log     = __('Sent to the customer by email, in their language.');
        } else {
            $relay = StoreStaffTask::run($assistant, [
                'subject'     => __('Pass on the purchasing team\'s answer to :customer', ['customer' => $customer->name]),
                'description' => implode("\n", array_filter([
                    __('The purchasing team answered a restock question from this customer in :reference. The shop cannot email it automatically, so please send it to the customer.', ['reference' => $task->reference]),
                    __('Customer: :name (:reference), :email, :phone', ['name' => $customer->name, 'reference' => $customer->reference, 'email' => $customer->email ?: '—', 'phone' => $customer->phone ?: '—']),
                    __('Answer in the customer\'s language:')."\n".$translated,
                    $translated !== $answer ? __('Answer as written:')."\n".$answer : null,
                ])),
                'department'  => StoreShoppingAssistantSalesLead::DEPARTMENT,
                'priority'    => ChatPriorityEnum::NORMAL->value,
                'model_type'  => 'Customer',
                'model_id'    => $customer->id,
                'data'        => ['kind' => 'shopping_assistant_restock_answer_relay', 'organisation_id' => $customer->organisation_id, 'restock_task_id' => $task->id],
            ]);
            $channel = 'customer_services';
            $log     = __('This shop cannot email it automatically, so customer services was asked to send it: :reference', ['reference' => $relay->reference]);
        }

        $task->update(['data' => [...$task->data, 'answer' => $answer, 'answer_sent_at' => now()->toIso8601String(), 'answer_channel' => $channel, 'answered_by' => $actor->id]]);
        if ($task->conversation) {
            SendStaffMessage::run($task->conversation, $assistant, ['body' => $log]);
        }

        return $channel;
    }

    private function lastAnswerInThread(StaffTask $task, User $assistant): string
    {
        $statusLines = [...array_values(StaffTaskStatusEnum::labels()), __('I will take this one')];
        $assigned    = __('Assigned to :name', ['name' => '']);

        return trim((string) $task->conversation?->messages()
            ->where('user_id', '!=', $assistant->id)
            ->latest('id')
            ->get(['body'])
            ->map(fn ($message) => trim((string) $message->body))
            ->first(fn (string $body) => $body !== '' && !in_array($body, $statusLines, true) && !str_starts_with($body, $assigned)));
    }

    private function translate(string $answer, ?string $locale): string
    {
        $english = Language::where('code', 'en')->first();
        $target  = $locale ? Language::where('code', $locale)->first() : null;

        if (!$english || !$target || $target->id === $english->id) {
            return $answer;
        }

        try {
            return Translate::run($answer, $english, $target, 'email');
        } catch (Throwable $exception) {
            Log::warning('Restock answer translation failed: '.$exception->getMessage());

            return $answer;
        }
    }
}
