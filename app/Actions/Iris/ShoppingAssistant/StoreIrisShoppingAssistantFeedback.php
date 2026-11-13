<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * The customer's thumbs up or down on one answer of their own conversation; null takes it back.
 * Kept apart from staff_rating, which the chat team sets from a hand-over.
 */
class StoreIrisShoppingAssistantFeedback extends IrisAction
{
    public function handle(ShoppingAssistantConversation $conversation, int $messageId, ?string $feedback): void
    {
        $message = $conversation->messages()
            ->where('id', $messageId)
            ->where('role', ShoppingAssistantMessageRoleEnum::ASSISTANT)
            ->firstOrFail();

        $message->update(['feedback' => $feedback, 'feedback_at' => $feedback ? now() : null]);
    }

    public function rules(): array
    {
        return [
            'message_id' => ['required', 'integer'],
            'feedback'   => ['present', 'nullable', 'string', 'in:up,down'],
        ];
    }

    public function asController(ShoppingAssistantConversation $conversation, ActionRequest $request): Response
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        abort_unless(
            $customerId && $conversation->customer_id === $customerId && $conversation->website_id === $this->website->id && $conversation->wiped_at === null && $conversation->status !== ShoppingAssistantConversationStatusEnum::CLEARED && GetShoppingAssistantSettings::run($this->website, $conversation->customer),
            404
        );

        $this->handle($conversation, (int) $this->validatedData['message_id'], Arr::get($this->validatedData, 'feedback'));

        return response()->noContent();
    }
}
