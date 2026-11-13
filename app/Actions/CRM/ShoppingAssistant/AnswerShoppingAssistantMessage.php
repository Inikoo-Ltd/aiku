<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Answers a customer message stored by the storefront, off the request: the storefront sits behind a
 * 30 s first-byte timeout and a turn can take up to 90 s. A failure stores the fallback answer, so the
 * conversation never waits for ever; a worker killed at the timeout stores no answer and the
 * conversation stops being pending after ShoppingAssistantConversation::PENDING_SECONDS. The model
 * calls a failed turn made are already in the conversation totals and the budget; the fallback
 * message is linked to them and shows their totals, nothing is added to the conversation again.
 */
class AnswerShoppingAssistantMessage
{
    use AsAction;

    public string $jobQueue = 'urgent';
    public int $jobTimeout = 150;
    public int $jobTries = 1;

    public function handle(ShoppingAssistantMessage $customerMessage): void
    {
        $conversation = $customerMessage->conversation;

        if ($conversation->messages()->where('id', '>', $customerMessage->id)->where('role', ShoppingAssistantMessageRoleEnum::ASSISTANT)->exists()) {
            return;
        }

        try {
            AskShoppingAssistant::make()->answer($conversation, $customerMessage);
        } catch (Throwable $exception) {
            report($exception);

            $calls  = DB::table('shopping_assistant_model_calls')->where('customer_message_id', $customerMessage->id)->whereNull('shopping_assistant_message_id');
            $totals = (clone $calls)->selectRaw('count(*) as model_calls, coalesce(sum(prompt_tokens), 0) as prompt_tokens, coalesce(sum(completion_tokens), 0) as completion_tokens, coalesce(sum(cost), 0) as cost')->first();

            $fallback = $conversation->messages()->create([
                'role'              => ShoppingAssistantMessageRoleEnum::ASSISTANT,
                'body'              => __('Sorry, I could not find an answer just now. Please try again.', [], $conversation->language_code),
                'flags'             => ['model_unavailable'],
                'model_calls'       => (int) $totals->model_calls,
                'prompt_tokens'     => (int) $totals->prompt_tokens,
                'completion_tokens' => (int) $totals->completion_tokens,
                'cost'              => (float) $totals->cost,
            ]);
            $calls->update(['shopping_assistant_message_id' => $fallback->id]);
            $conversation->increment('messages_count', 1, ['last_message_at' => now()]);
        }

        rescue(fn () => AlertShoppingAssistantHeavyCustomer::run($conversation));
    }
}
