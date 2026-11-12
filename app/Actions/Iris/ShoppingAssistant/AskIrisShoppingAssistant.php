<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantMessage;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantRefusal;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stores the customer's message and queues the answer; the page polls ShowIrisShoppingAssistantConversation.
 */
class AskIrisShoppingAssistant extends IrisAction
{
    public function handle(Customer $customer, ?ShoppingAssistantConversation $conversation, string $message, int $webUserId): JsonResponse
    {
        if ($conversation?->isPending()) {
            return response()->json(['pending' => true], 409);
        }

        $conversation ??= StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH, ['web_user_id' => $webUserId]);

        $customerMessage = AskShoppingAssistant::make()->storeCustomerMessage($conversation, $message);
        AnswerShoppingAssistantMessage::dispatch($customerMessage);

        return response()->json([
            'conversation' => $conversation->ulid,
            'message_id'   => $customerMessage->id,
            'pending'      => true,
        ], 202);
    }

    public function rules(): array
    {
        return [
            'message'      => ['required', 'string', 'max:1000'],
            'conversation' => ['sometimes', 'nullable', 'string', 'ulid'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::find($customerId) : null;
        abort_unless($customer, 404);
        if (!GetShoppingAssistantSettings::run($this->website, $customer, true)) {
            if (GetShoppingAssistantSettings::run($this->website, $customer)) {
                StoreShoppingAssistantRefusal::afterResponse($this->website, $customer->id, StoreShoppingAssistantRefusal::BUDGET, $request->input('message'));
            }
            abort(404);
        }

        $conversation = null;
        if ($ulid = Arr::get($this->validatedData, 'conversation')) {
            $conversation = ShoppingAssistantConversation::where('ulid', $ulid)
                ->where('customer_id', $customer->id)
                ->where('website_id', $this->website->id)
                ->where('status', ShoppingAssistantConversationStatusEnum::OPEN)
                ->whereNull('wiped_at')
                ->first();
            abort_unless($conversation, 404);
        }

        return $this->handle($customer, $conversation, $this->validatedData['message'], $request->user()->id);
    }
}
