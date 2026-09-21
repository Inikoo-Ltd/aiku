<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallContactTypeEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Chat\ChatPhoneCall;
use App\Models\Chat\ChatSession;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EndChatPhoneCall
{
    use AsAction;
    use WithChatPhoneCall;

    /**
     * @param  array{notes: string, contact_type: string, customer_id?: int|null, chat_session_id?: int|null, contact_name?: string|null, shop_id?: int|null}  $data
     */
    public function handle(ChatPhoneCall $call, User $user, array $data): ChatPhoneCall
    {
        $contactType = ChatPhoneCallContactTypeEnum::from($data['contact_type']);
        $shopIds     = $this->workableShopIdsFor($user);
        $customer    = isset($data['customer_id']) ? Customer::whereIn('shop_id', $shopIds)->find($data['customer_id']) : null;
        $session     = isset($data['chat_session_id']) ? ChatSession::whereIn('shop_id', $shopIds)->find($data['chat_session_id']) : null;

        if ((isset($data['customer_id']) && !$customer) || (isset($data['chat_session_id']) && !$session)) {
            throw ValidationException::withMessages([
                'contact' => __('That contact does not belong to a shop you work.'),
            ]);
        }

        $shop        = $this->assertShopIsWorkable($user, $data['shop_id'] ?? $call->shop_id);

        $call = $this->closeCall($call, ChatPhoneCallStatusEnum::COMPLETED, [
            'organisation_id' => $shop?->organisation_id ?? $call->organisation_id,
            'shop_id'         => $shop?->id ?? $call->shop_id,
            'contact_type'    => $contactType,
            'customer_id'     => $customer?->id,
            'chat_session_id' => $session?->id,
            'contact_name'    => $this->resolveContactName($data, $customer, $session),
            'notes'           => trim($data['notes']),
        ]);

        $this->writeToConversation($call, $user, $customer, $session);

        return $call;
    }

    /**
     * The call is worth nothing to the next agent if it only lives on a report page, so it is
     * written into the conversation the contact already has. A contact with no conversation is
     * left alone: there is nowhere to put it, and the calls index still holds it.
     */
    private function writeToConversation(ChatPhoneCall $call, User $user, ?Customer $customer, ?ChatSession $session): void
    {
        $session ??= $this->latestSessionForCustomer($customer);

        if (!$session) {
            return;
        }

        StoreChatEvent::make()->customEvent(
            $session,
            ChatEventTypeEnum::PHONE_CALL,
            ChatActorTypeEnum::AGENT,
            $call->chat_agent_id,
            [
                'chat_phone_call_id' => $call->id,
                'agent_name'         => $user->contact_name,
                'contact_name'       => $call->contact_name,
                'notes'              => $call->notes,
                'duration_seconds'   => $call->duration_seconds,
                'started_at'         => $call->started_at->toIso8601String(),
            ]
        );
    }

    private function latestSessionForCustomer(?Customer $customer): ?ChatSession
    {
        if (!$customer) {
            return null;
        }

        $webUserIds = WebUser::where('customer_id', $customer->id)->pluck('id')->all();

        if ($webUserIds === []) {
            return null;
        }

        return ChatSession::whereIn('web_user_id', $webUserIds)
            ->latest('created_at')
            ->first();
    }

    private function resolveContactName(array $data, ?Customer $customer, ?ChatSession $session): ?string
    {
        $typed = trim((string) ($data['contact_name'] ?? ''));

        if ($typed !== '') {
            return $typed;
        }

        if ($customer) {
            return $customer->name;
        }

        return $session ? GetChatPhoneCallGuests::make()->nameFor($session) : null;
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user  = $request->user();
        $agent = $this->chatAgentProfileFor($user);
        $call  = $this->activeCallFor($agent);

        if (!$call) {
            throw ValidationException::withMessages([
                'call' => __('There is no phone call running.'),
            ]);
        }

        $call = $this->handle($call, $user, $request->validated());

        return response()->json($this->callPayload(null, $user) + ['ended' => ['id' => $call->id]]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->workableShopIdsFor($request->user()) !== [];
    }

    public function rules(): array
    {
        return [
            // The notes are the reason this feature exists: a call with nothing written about it
            // tells the next person nothing, so it cannot be filed.
            'notes'           => ['required', 'string', 'max:5000'],
            'contact_type'    => ['required', 'string', 'in:'.implode(',', ChatPhoneCallContactTypeEnum::values())],
            'customer_id'     => ['nullable', 'integer', 'exists:customers,id', 'required_if:contact_type,customer'],
            'chat_session_id' => ['nullable', 'integer', 'exists:chat_sessions,id'],
            'contact_name'    => ['nullable', 'string', 'max:255'],
            'shop_id'         => ['nullable', 'integer'],
        ];
    }
}
