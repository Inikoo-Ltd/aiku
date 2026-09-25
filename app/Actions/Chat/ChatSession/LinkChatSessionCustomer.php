<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\CRM\Customer;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A regular customer writing from an address that is not the one on their account matches
 * nobody, so the agent picks them by hand. The pick links this conversation only: nothing is
 * written to the customer, so the address never becomes theirs. Next time the same address
 * writes in, the earlier pick comes back as a suggestion the agent confirms with one click.
 */
class LinkChatSessionCustomer
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
        ];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only authenticated agents can link a customer')], 403);
        }

        $customer = Customer::where('shop_id', $chatSession->shop_id)
            ->whereHas('webUsers')
            ->find($request->validated()['customer_id']);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => __('That customer has no login in this shop')], 422);
        }

        $chatSession->update([
            'suggested_customer_id'   => $customer->id,
            'suggestion_basis'        => SuggestChatSessionCustomer::BASIS_MANUAL,
            'suggestion_rejected_at'  => null,
        ]);

        return ConfirmSuggestedChatCustomer::make()->asController($chatSession);
    }

    /**
     * A wrong link shows another customer's orders to whoever reads the conversation, so it can
     * be undone by any agent. The address is then never suggested for that customer again.
     */
    public function unlink(ChatSession $chatSession): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only authenticated agents can unlink a customer')], 403);
        }

        $customer = $chatSession->webUser?->customer;

        if (!$customer) {
            return response()->json(['success' => false, 'message' => __('This conversation is not linked to a customer')], 422);
        }

        $chatSession->update(['web_user_id' => null, 'suggested_customer_id' => null, 'suggestion_rejected_at' => now()]);

        ConfirmSuggestedChatCustomer::auditOnCustomer($customer, 'chat_unlinked', $chatSession, $agent);
        StoreChatEvent::make()->handle(chatSession: $chatSession, eventType: ChatEventTypeEnum::NOTE, actorType: ChatActorTypeEnum::AGENT, actorId: $agent->id, payload: [
            'action_type'   => 'customer_unlinked',
            'customer_id'   => $customer->id,
            'customer_name' => $customer->name,
            'agent_name'    => $agent->user?->contact_name,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * @return array<int, array{id: int, name: ?string, reference: ?string, email: ?string}>
     */
    public function search(ChatSession $chatSession, ActionRequest $request): array
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            abort(403);
        }

        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return [];
        }

        $customers = Customer::where('shop_id', $chatSession->shop_id)->whereHas('webUsers');

        foreach (preg_split('/\s+/', mb_strtolower($query)) as $token) {
            $customers->where('searchable_text', 'ILIKE', "%{$token}%");
        }

        return $customers->orderBy('name')->limit(10)->get(['id', 'name', 'reference', 'email'])->toArray();
    }
}
