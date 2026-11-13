<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantMessage;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantBudget;
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
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stores the customer's message and queues the answer; the page polls ShowIrisShoppingAssistantConversation.
 */
class AskIrisShoppingAssistant extends IrisAction
{
    public function handle(Customer $customer, ?ShoppingAssistantConversation $conversation, string $message, int $webUserId, ShoppingAssistantEntryPointEnum $entryPoint = ShoppingAssistantEntryPointEnum::SEARCH): JsonResponse
    {
        if ($conversation?->isPending()) {
            return response()->json(['pending' => true], 409);
        }

        $conversation ??= StartShoppingAssistantConversation::run($customer, $entryPoint, ['web_user_id' => $webUserId]);

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
            'entry_point'  => ['sometimes', 'nullable', Rule::in([ShoppingAssistantEntryPointEnum::SEARCH->value, ShoppingAssistantEntryPointEnum::CHAT->value])],
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
                $reason = GetShoppingAssistantBudget::run($this->website)['paused'] ? StoreShoppingAssistantRefusal::BUDGET : StoreShoppingAssistantRefusal::CUSTOMER_CAP;
                StoreShoppingAssistantRefusal::afterResponse($this->website, $customer->id, $reason, $request->input('message'));
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

        return $this->handle($customer, $conversation, $this->validatedData['message'], $request->user()->id, ShoppingAssistantEntryPointEnum::tryFrom((string) Arr::get($this->validatedData, 'entry_point')) ?? ShoppingAssistantEntryPointEnum::SEARCH);
    }
}
