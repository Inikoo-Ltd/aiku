<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantHandOverRoute;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\CRM\ShoppingAssistant\HandOverShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The customer asks the assistant page to pass their question to a person: the button under an
 * answer that offered one (offer_message_id), or the Talk to a person link with a typed question
 * (message, which starts the conversation when there is none). The gate is the assistant's own
 * settings without the AI budget check, as a spent budget must not stop anyone reaching a person.
 * The page then reads the new messages and, for a live chat, opens the website chat window on
 * the chat ulid returned here. A typed question repeated within a minute answers with the first
 * hand-over, and a customer over the daily limit is refused with 429, both before any conversation
 * is started, so neither leaves an empty one behind.
 */
class HandOverIrisShoppingAssistant extends IrisAction
{
    public function handle(Customer $customer, ?ShoppingAssistantConversation $conversation, ?ShoppingAssistantMessage $offerMessage, ?string $message, int $webUserId): JsonResponse
    {
        $handOver = HandOverShoppingAssistantConversation::make();
        $result   = null;

        if (!$conversation) {
            $result = $handOver->recentTypedHandOver($customer, $this->website, (string) $message);
            abort_if(!$result && $handOver->limitReached($customer, $this->website), 429);
            $conversation = $result
                ? $result['hand_over']->conversation
                : StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH, ['web_user_id' => $webUserId]);
        }

        $result ??= $handOver->handle($conversation, $offerMessage, $message);
        abort_unless($result, 404);

        return response()->json([
            'conversation' => $result['hand_over']->conversation->ulid,
            'message_id'   => $result['message']->id,
            'route'        => $result['hand_over']->route->value,
            'chat_session' => $result['hand_over']->route === ShoppingAssistantHandOverRouteEnum::EMAIL_CHAT ? null : $result['chat_session']->ulid,
        ]);
    }

    public function rules(): array
    {
        return [
            'conversation'     => ['sometimes', 'nullable', 'string', 'ulid'],
            'offer_message_id' => ['sometimes', 'nullable', 'integer'],
            'message'          => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::find($customerId) : null;
        abort_unless($customer && GetShoppingAssistantSettings::run($this->website, $customer) && GetShoppingAssistantHandOverRoute::isOffered($this->website, $customer), 404);

        $conversation = null;
        if ($ulid = Arr::get($this->validatedData, 'conversation')) {
            $conversation = ShoppingAssistantConversation::where('ulid', $ulid)
                ->where('customer_id', $customer->id)
                ->where('website_id', $this->website->id)
                ->where('status', '!=', ShoppingAssistantConversationStatusEnum::CLEARED)
                ->first();
            abort_unless($conversation, 404);
        }

        $message = Arr::get($this->validatedData, 'message');
        if (!$conversation && blank($message)) {
            throw ValidationException::withMessages(['message' => __('Please write your question for our team.')]);
        }

        $offerMessage = null;
        if ($conversation && $offerId = Arr::get($this->validatedData, 'offer_message_id')) {
            $offerMessage = $conversation->messages()->find($offerId);
            if (!$offerMessage && blank($message)) {
                throw ValidationException::withMessages(['offer_message_id' => __('That answer does not offer a person.')]);
            }
        }

        return $this->handle($customer, $conversation, $offerMessage, $message, $request->user()->id);
    }
}
