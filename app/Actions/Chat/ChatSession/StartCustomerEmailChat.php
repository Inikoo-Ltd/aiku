<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Writing to a customer from their record opens the same email conversation an incoming mail
 * would have opened, so their reply lands in that thread instead of starting a second one.
 */
class StartCustomerEmailChat extends OrgAction
{
    use WithChatAgentAuthorisation;

    private Customer $customer;

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $user instanceof User && $this->userCanActOnChatOnShop($user, $this->customer->shop);
    }

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): ChatSession
    {
        if (!self::canBeStarted($customer)) {
            throw ValidationException::withMessages([
                'message' => __('This customer has no email address, or the shop has no mailbox connected.'),
            ]);
        }

        $agent = $this->chatAgentProfileFor(Auth::user());

        $session = StoreChatSession::run([
            'shop_id'             => $customer->shop_id,
            'trusted_web_user_id' => $this->webUserFor($customer)?->id,
            'language_id'         => $customer->shop->language_id,
            'priority'            => ChatPriorityEnum::NORMAL,
            'channel'             => ChatChannelEnum::EMAIL,
        ]);

        $session->update([
            'status'   => ChatSessionStatusEnum::ACTIVE->value,
            'metadata' => array_merge($session->metadata ?? [], [
                'email_subject'   => $modelData['subject'],
                'email_from'      => $customer->email,
                'email_from_name' => $customer->contact_name ?? $customer->name,
                'name'            => $customer->contact_name ?? $customer->name,
                'email'           => $customer->email,
            ]),
        ]);

        $session->assignments()->create([
            'chat_agent_id' => $agent->id,
            'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
            'note'          => 'Email started from the customer record',
            'assigned_at'   => now(),
        ]);

        SendChatMessage::run($session, [
            'message_text' => $modelData['message'],
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::AGENT->value,
            'sender_id'    => $agent->id,
        ]);

        return $session;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): ChatSession
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData): ChatSession
    {
        $this->asAction = true;
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    public function htmlResponse(ChatSession $chatSession): RedirectResponse
    {
        return redirect()->route('grp.org.chat.inbox.conversation', [
            'organisation' => $chatSession->shop->organisation->slug,
            'chatSession'  => $chatSession->ulid,
        ]);
    }

    /**
     * The mail goes to the customer's own address, so the login carrying that address is the one
     * the conversation belongs to. Any other login of theirs still reaches the right customer.
     */
    private function webUserFor(Customer $customer): ?WebUser
    {
        return $customer->webUsers()->where('email', $customer->email)->first()
            ?? $customer->webUsers()->first();
    }

    /**
     * A shop with no mailbox connected can send nothing, and a customer with no address has
     * nowhere to be written to: neither is offered the button.
     */
    public static function canBeStarted(Customer $customer): bool
    {
        return filled($customer->email)
            && filled(Arr::get($customer->shop->settings, 'gmail.email'));
    }
}
