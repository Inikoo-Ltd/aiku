<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\StoreMetaChatEvent;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A regular customer writing from an address that is not the one on their account matches
 * nobody, so the agent picks them by hand. The pick links this conversation only: nothing is
 * written to the customer, so the address never becomes theirs. Next time the same address
 * writes in, the earlier pick comes back as a suggestion the agent confirms with one click.
 *
 * WhatsApp links the conversation to the customer itself rather than to one of their logins,
 * so there any customer of the shop can be picked, login or not.
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
        return $this->link($chatSession, (int) $request->validated()['customer_id']);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        return $this->link($metaChatSession, (int) Validator::make($request->all(), $this->rules())->validate()['customer_id']);
    }

    private function link(ChatSession|MetaChatSession $chatSession, int $customerId): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only authenticated agents can link a customer')], 403);
        }

        $customer = $this->candidates($chatSession)->find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => $chatSession instanceof MetaChatSession ? __('That customer is not in this shop') : __('That customer has no login in this shop'),
            ], 422);
        }

        $chatSession->update([
            'suggested_customer_id'   => $customer->id,
            'suggestion_basis'        => SuggestChatSessionCustomer::BASIS_MANUAL,
            'suggestion_rejected_at'  => null,
        ]);

        return $chatSession instanceof MetaChatSession
            ? ConfirmSuggestedChatCustomer::make()->inMetaChatSession($chatSession)
            : ConfirmSuggestedChatCustomer::make()->asController($chatSession);
    }

    /**
     * A wrong link shows another customer's orders to whoever reads the conversation, so it can
     * be undone by any agent. The address is then never suggested for that customer again.
     */
    public function unlink(ChatSession $chatSession): JsonResponse
    {
        return $this->unlinkFrom($chatSession);
    }

    public function unlinkInMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->unlinkFrom($metaChatSession);
    }

    private function unlinkFrom(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only authenticated agents can unlink a customer')], 403);
        }

        $customer = $chatSession instanceof MetaChatSession ? $chatSession->customer : $chatSession->webUser?->customer;

        if (!$customer) {
            return response()->json(['success' => false, 'message' => __('This conversation is not linked to a customer')], 422);
        }

        $chatSession->update([
            $chatSession instanceof MetaChatSession ? 'customer_id' : 'web_user_id' => null,
            'suggested_customer_id'  => null,
            'suggestion_rejected_at' => now(),
        ]);

        ConfirmSuggestedChatCustomer::auditOnCustomer($customer, 'chat_unlinked', $chatSession, $agent);

        $payload = [
            'action_type'   => 'customer_unlinked',
            'customer_id'   => $customer->id,
            'customer_name' => $customer->name,
            'agent_name'    => $agent->user?->contact_name,
        ];

        if ($chatSession instanceof MetaChatSession) {
            StoreMetaChatEvent::make()->handle($chatSession, ChatEventTypeEnum::NOTE, ChatActorTypeEnum::AGENT, $agent->id, $payload);
        } else {
            StoreChatEvent::make()->handle(chatSession: $chatSession, eventType: ChatEventTypeEnum::NOTE, actorType: ChatActorTypeEnum::AGENT, actorId: $agent->id, payload: $payload);
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array<int, array{id: int, name: ?string, reference: ?string, email: ?string, phone: ?string}>
     */
    public function search(ChatSession $chatSession, ActionRequest $request): array
    {
        return $this->searchFor($chatSession, (string) $request->query('q'));
    }

    /**
     * @return array<int, array{id: int, name: ?string, reference: ?string, email: ?string, phone: ?string}>
     */
    public function searchInMetaChatSession(MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        return $this->searchFor($metaChatSession, (string) $request->query('q'));
    }

    /**
     * @return array<int, array{id: int, name: ?string, reference: ?string, email: ?string, phone: ?string}>
     */
    private function searchFor(ChatSession|MetaChatSession $chatSession, string $query): array
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            abort(403);
        }

        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $customers = $this->candidates($chatSession);

        foreach (preg_split('/\s+/', mb_strtolower($query)) as $token) {
            $customers->where('searchable_text', 'ILIKE', "%{$token}%");
        }

        return $customers->orderBy('name')->limit(10)->get(['id', 'name', 'reference', 'email', 'phone'])->toArray();
    }

    private function candidates(ChatSession|MetaChatSession $chatSession): Builder
    {
        return Customer::where('shop_id', $chatSession->shop_id)
            ->when($chatSession instanceof ChatSession, fn (Builder $query) => $query->whereHas('webUsers'));
    }
}
