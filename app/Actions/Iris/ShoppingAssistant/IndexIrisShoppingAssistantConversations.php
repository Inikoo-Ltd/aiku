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
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

/**
 * The signed-in customer's recent conversations on this website, newest first, for the history
 * list of the assistant page. A conversation the customer cleared is never listed, and each one
 * is titled by its first question.
 */
class IndexIrisShoppingAssistantConversations extends IrisAction
{
    public const int LIMIT = 30;

    /**
     * @return array{conversations: array<int, array{conversation: string, title: string, last_message_at: string|null}>}
     */
    public function handle(Customer $customer): array
    {
        $conversations = ShoppingAssistantConversation::query()
            ->where('customer_id', $customer->id)
            ->where('website_id', $this->website->id)
            ->where('status', '!=', ShoppingAssistantConversationStatusEnum::CLEARED)
            ->addSelect([
                'ulid',
                'last_message_at',
                'created_at',
                'title' => ShoppingAssistantMessage::select('body')
                    ->whereColumn('shopping_assistant_messages.shopping_assistant_conversation_id', 'shopping_assistant_conversations.id')
                    ->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)
                    ->orderBy('id')
                    ->limit(1),
            ])
            ->orderByRaw('coalesce(last_message_at, created_at) desc')
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (ShoppingAssistantConversation $conversation) => filled($conversation->title));

        return [
            'conversations' => $conversations->map(fn (ShoppingAssistantConversation $conversation) => [
                'conversation'    => $conversation->ulid,
                'title'           => Str::limit(Str::squish($conversation->title), 80),
                'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $customer = Customer::find($this->signedInCustomerId());
        abort_unless($customer && GetShoppingAssistantSettings::run($this->website, $customer), 404);

        return $this->handle($customer);
    }
}
