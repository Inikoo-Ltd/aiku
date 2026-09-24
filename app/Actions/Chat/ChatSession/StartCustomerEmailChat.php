<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Writing to a customer from their record opens the same email conversation an incoming mail
 * would have opened, so their reply lands in that thread instead of starting a second one.
 * The agent may send it to another address of theirs, such as a colleague at the same business:
 * the reply still comes back by thread, and the address is remembered as linked by hand, so the
 * next mail from it is suggested as this customer. From the inbox the agent can also write to
 * somebody who is not a customer yet, and save them as a prospect on the way.
 */
class StartCustomerEmailChat extends OrgAction
{
    use WithChatAgentAuthorisation;

    private ?Customer $customer = null;

    public function rules(): array
    {
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'email'         => ['sometimes', 'nullable', 'email'],
            'attachments'   => ['sometimes', 'array', 'max:10'],
            'attachments.*' => [File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'pptx'])->max(20 * 1024)],
        ];

        if (!$this->customer) {
            $rules['email']            = ['required', 'email'];
            $rules['save_as_prospect'] = ['sometimes', 'boolean'];
            $rules['contact_name']     = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['company_name']     = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $user instanceof User && $this->userCanActOnChatOnShop($user, $this->shop);
    }

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, ?Customer $customer, array $modelData): ChatSession
    {
        $recipient = Arr::get($modelData, 'email') ?: $customer?->email;

        $customer ??= Customer::where('shop_id', $shop->id)->where('email', $recipient)->first();

        if (blank($recipient) || blank(Arr::get($shop->settings, 'gmail.email'))) {
            throw ValidationException::withMessages([
                'message' => __('This customer has no email address, or the shop has no mailbox connected.'),
            ]);
        }

        $isCustomersOwnAddress = $customer && strcasecmp($recipient, (string) $customer->email) === 0;
        $recipientName         = $isCustomersOwnAddress ? ($customer->contact_name ?? $customer->name) : Arr::get($modelData, 'contact_name');

        if (!$customer && Arr::get($modelData, 'save_as_prospect')) {
            $this->saveAsProspect($shop, $recipient, $modelData);
        }

        $agent = $this->chatAgentProfileFor(Auth::user());

        $session = StoreChatSession::run([
            'shop_id'             => $shop->id,
            'trusted_web_user_id' => $customer ? $this->webUserFor($customer)?->id : null,
            'language_id'         => $shop->language_id,
            'priority'            => ChatPriorityEnum::NORMAL,
            'channel'             => ChatChannelEnum::EMAIL,
        ]);

        $session->update([
            'status'   => ChatSessionStatusEnum::ACTIVE->value,
            'metadata' => array_merge($session->metadata ?? [], [
                'email_subject'   => $modelData['subject'],
                'email_from'      => $recipient,
                'email_from_name' => $recipientName,
                'name'            => $recipientName ?? $recipient,
                'email'           => $recipient,
            ]),
        ]);

        if ($customer && !$isCustomersOwnAddress && $session->web_user_id) {
            $session->update([
                'suggested_customer_id' => $customer->id,
                'suggestion_basis'      => SuggestChatSessionCustomer::BASIS_MANUAL,
            ]);
            ConfirmSuggestedChatCustomer::auditOnCustomer($customer, 'chat_linked', $session, $agent);
        }

        $session->assignments()->create([
            'chat_agent_id' => $agent->id,
            'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
            'note'          => $customer ? 'Email started from the customer record' : 'Email started from the inbox',
            'assigned_at'   => now(),
        ]);

        SendChatMessage::run($session, [
            'message_text' => $modelData['message'],
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::AGENT->value,
            'sender_id'    => $agent->id,
            'attachments'  => Arr::get($modelData, 'attachments', []),
        ]);

        return $session;
    }

    /**
     * The prospect is credited to the agent who brought them in. An address already on a
     * prospect is left as it is: the agent is writing to them, not re-registering them.
     *
     * @throws \Throwable
     */
    private function saveAsProspect(Shop $shop, string $email, array $modelData): void
    {
        if (Prospect::where('shop_id', $shop->id)->where('email', $email)->exists()) {
            return;
        }

        StoreProspect::make()->action($shop, [
            'email'        => $email,
            'contact_name' => Arr::get($modelData, 'contact_name'),
            'company_name' => Arr::get($modelData, 'company_name'),
            'user_id'      => Auth::id(),
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): ChatSession
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer->shop, $customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inShop(Shop $shop, ActionRequest $request): ChatSession
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, null, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop|Customer $parent, array $modelData): ChatSession
    {
        $this->asAction = true;
        $this->customer = $parent instanceof Customer ? $parent : null;
        $shop           = $parent instanceof Customer ? $parent->shop : $parent;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->customer, $this->validatedData);
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
