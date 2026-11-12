<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A click on a card the assistant showed, stored once per message and product. Only clicks come
 * from the browser; basket adds are recorded on the server by RecordShoppingAssistantBasketAdd.
 */
class StoreIrisShoppingAssistantEvent extends IrisAction
{
    public function handle(ShoppingAssistantConversation $conversation, int $messageId, int $productId): void
    {
        $shown = $conversation->events()
            ->where('type', ShoppingAssistantEventTypeEnum::SHOWN)
            ->where('shopping_assistant_message_id', $messageId)
            ->where('product_id', $productId)
            ->first();

        if (!$shown) {
            throw ValidationException::withMessages(['product_id' => __('This product was not shown by the assistant.')]);
        }

        $conversation->events()->firstOrCreate([
            'shopping_assistant_message_id' => $messageId,
            'type'                          => ShoppingAssistantEventTypeEnum::CLICKED,
            'product_id'                    => $productId,
        ], [
            'group'       => $shown->group,
            'position'    => $shown->position,
            'reason_code' => $shown->reason_code,
        ]);
    }

    public function rules(): array
    {
        return [
            'type'       => ['required', 'string', 'in:'.ShoppingAssistantEventTypeEnum::CLICKED->value],
            'product_id' => ['required', 'integer'],
            'message_id' => ['required', 'integer'],
        ];
    }

    public function asController(ShoppingAssistantConversation $conversation, ActionRequest $request): Response
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        abort_unless(
            $customerId && $conversation->customer_id === $customerId && $conversation->website_id === $this->website->id && $conversation->wiped_at === null && GetShoppingAssistantSettings::run($this->website, $conversation->customer),
            404
        );

        $this->handle($conversation, (int) $this->validatedData['message_id'], (int) $this->validatedData['product_id']);

        return response()->noContent();
    }
}
