<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\StoreMetaChatEvent;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * The agent's answer to "this is probably customer X": yes links the conversation to the
 * customer, no takes the suggestion away for good. Either way it is an agent who decided, and
 * it is written into the conversation's record.
 */
class ConfirmSuggestedChatCustomer
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatSession|MetaChatSession $chatSession, ChatAgent $agent, bool $confirmed): ?Customer
    {
        $customer = Customer::where('shop_id', $chatSession->shop_id)->find($chatSession->suggested_customer_id);

        if ($confirmed && $customer) {
            $chatSession->update($chatSession instanceof MetaChatSession
                ? ['customer_id' => $customer->id]
                : ['web_user_id' => $customer->webUsers()->oldest('id')->value('id')]);
            self::auditOnCustomer($customer, 'chat_linked', $chatSession, $agent);
        } else {
            $chatSession->update(['suggestion_rejected_at' => now()]);
        }

        $payload = [
            'action_type'   => $confirmed && $customer ? 'customer_confirmed' : 'customer_suggestion_rejected',
            'customer_id'   => $customer?->id,
            'customer_name' => $customer?->name,
            'basis'         => $chatSession->suggestion_basis,
            'agent_name'    => $agent->user?->contact_name,
        ];

        if ($chatSession instanceof MetaChatSession) {
            StoreMetaChatEvent::make()->handle($chatSession, ChatEventTypeEnum::NOTE, ChatActorTypeEnum::AGENT, $agent->id, $payload);
        } else {
            StoreChatEvent::make()->handle(chatSession: $chatSession, eventType: ChatEventTypeEnum::NOTE, actorType: ChatActorTypeEnum::AGENT, actorId: $agent->id, payload: $payload);
        }

        return $confirmed ? $customer : null;
    }

    /**
     * The chat session's own audit says a web user id changed; the customer's history is where
     * somebody looks when a conversation shows up on the wrong account, so it is written there too.
     */
    public static function auditOnCustomer(Customer $customer, string $event, ChatSession|MetaChatSession $chatSession, ChatAgent $agent): void
    {
        $customer->auditEvent     = $event;
        $customer->isCustomEvent  = true;
        $customer->auditCustomOld = [];
        $customer->auditCustomNew = [
            'chat_session' => $chatSession->ulid,
            'channel'      => $chatSession instanceof MetaChatSession ? 'whatsapp' : $chatSession->channel?->value,
            'sender'       => $chatSession instanceof MetaChatSession ? $chatSession->phone_number : data_get($chatSession->metadata, 'email'),
            'basis'        => $chatSession->suggestion_basis,
            'agent'        => $agent->user?->contact_name,
        ];
        Event::dispatch(new AuditCustom($customer));
        $customer->isCustomEvent  = false;
        $customer->auditCustomOld = [];
        $customer->auditCustomNew = [];
    }

    public function asController(ChatSession $chatSession): JsonResponse
    {
        return $this->respond($chatSession, true);
    }

    public function reject(ChatSession $chatSession): JsonResponse
    {
        return $this->respond($chatSession, false);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->respond($metaChatSession, true);
    }

    public function rejectInMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->respond($metaChatSession, false);
    }

    private function respond(ChatSession|MetaChatSession $chatSession, bool $confirmed): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only authenticated agents can link a customer')], 403);
        }

        if (!$chatSession->suggested_customer_id || $chatSession->suggestion_rejected_at) {
            return response()->json(['success' => false, 'message' => __('There is no suggested customer on this conversation')], 422);
        }

        $customer = $this->handle($chatSession, $agent, $confirmed);

        if ($confirmed && !$customer) {
            return response()->json(['success' => false, 'message' => __('The suggested customer no longer exists')], 422);
        }

        $chatSession->refresh();

        return response()->json([
            'success' => true,
            'data'    => [
                'confirmed' => $confirmed,
                'web_user'  => $chatSession instanceof ChatSession && $confirmed ? [
                    'id'    => $chatSession->web_user_id,
                    'name'  => $customer->name,
                    'email' => $customer->email,
                ] : null,
                'customer'  => $customer?->only(['id', 'name', 'email', 'phone', 'slug']),
            ],
        ]);
    }
}
