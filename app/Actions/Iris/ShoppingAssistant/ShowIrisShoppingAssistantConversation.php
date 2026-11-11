<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\Catalogue\Product\Json\WithIrisProductsInWebpage;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantEvent;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * The messages of a conversation, polled by the storefront while an answer is pending. The products
 * of each answer come back as the same cards the search results page shows, with the customer's
 * prices, stock and basket quantity; a product whose webpage went offline since is left out.
 * A conversation the customer cleared answers the poll that was waiting for it, so the page can
 * say so, and is not found from then on.
 */
class ShowIrisShoppingAssistantConversation extends IrisAction
{
    use WithIrisProductsInWebpage;

    /**
     * @return array{conversation: string, status: string, pending: bool, messages: array<int, array<string, mixed>>}
     */
    public function handle(ShoppingAssistantConversation $conversation, ?int $after): array
    {
        $messages = $conversation->messages()
            ->when($after, fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->get(['id', 'role', 'body', 'follow_ups', 'tool_calls', 'created_at']);

        $events = ShoppingAssistantEvent::whereIn('shopping_assistant_message_id', $messages->where('role', ShoppingAssistantMessageRoleEnum::ASSISTANT)->pluck('id'))
            ->where('type', ShoppingAssistantEventTypeEnum::SHOWN)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['shopping_assistant_message_id', 'product_id', 'group', 'reason']);

        $cards = $events->isEmpty() ? collect() : collect(IrisAuthenticatedProductsInWebpageResource::collection(
            $this->getBaseQuery('all')
                ->whereIn('products.id', $events->pluck('product_id')->unique()->values())
                ->select($this->getSelect())
                ->get()
        )->resolve())->keyBy('id');

        return [
            'conversation' => $conversation->ulid,
            'status'       => $conversation->status->value,
            'pending'      => $conversation->isPending(),
            'messages'     => $messages->map(function (ShoppingAssistantMessage $message) use ($events, $cards) {
                $data = [
                    'id'         => $message->id,
                    'role'       => $message->role->value,
                    'body'       => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ];

                if ($message->role === ShoppingAssistantMessageRoleEnum::ASSISTANT) {
                    $data['follow_ups']   = $message->follow_ups ?? [];
                    $data['basket_added'] = collect($message->tool_calls)->flatMap(fn ($toolCall) => $toolCall['added_product_ids'] ?? [])->values()->all();
                    $data['groups']     = array_fill_keys(AskShoppingAssistant::GROUPS, []);

                    foreach ($events->where('shopping_assistant_message_id', $message->id) as $event) {
                        $card = $cards->get($event->product_id);
                        if ($card && isset($data['groups'][$event->group])) {
                            $data['groups'][$event->group][] = array_merge($card, [
                                'assistant_reason'     => (string) $event->reason,
                                'assistant_message_id' => $message->id,
                            ]);
                        }
                    }
                }

                return $data;
            })->values()->all(),
        ];
    }

    /**
     * Replaces the paginated product list response of WithIrisProductsInWebpage.
     *
     * @param  array<string, mixed>  $conversation
     *
     * @return array<string, mixed>
     */
    public function jsonResponse(array $conversation): array
    {
        return $conversation;
    }

    public function rules(): array
    {
        return [
            'after' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function asController(ShoppingAssistantConversation $conversation, ActionRequest $request): array
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        abort_unless(
            $customerId && $conversation->customer_id === $customerId && $conversation->website_id === $this->website->id && GetShoppingAssistantSettings::run($this->website, $conversation->customer),
            404
        );

        $after = Arr::get($this->validatedData, 'after');

        abort_if($conversation->status === ShoppingAssistantConversationStatusEnum::CLEARED && $after === null, 404);

        return $this->handle($conversation, $after === null ? null : (int) $after);
    }
}
