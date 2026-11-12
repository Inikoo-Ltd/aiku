<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 10:12:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomers;
use App\Actions\Comms\Email\SendAccountErasedEmail;
use App\Actions\Comms\WhatsappSubscriber\SyncCustomerWhatsappSubscriber;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageTranslation;
use App\Models\Chat\ChatSession;
use App\Models\Comms\EmailAddress;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantEvent;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redirect;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class AnonymiseCustomer extends OrgAction
{
    public string $commandSignature = 'customer:anonymise {slug} {--reason=} {--keep-company-name} {--fraud-note=}';

    public const string ERASED = '[erased]';

    public const string AUDIT_EVENT = 'anonymised';

    public const string DELETED_CAUSE = 'anonymised';

    private Customer $customer;

    public const string HELD_AUDIT_EVENT = 'erasure_held';

    public const string HELD_DELETED_CAUSE = 'erasure_held';

    public function handle(Customer $customer, string $reason, bool $keepCompanyName = false, ?string $fraudNote = null): Customer
    {
        if (static::isAnonymised($customer) || static::isErasureHeld($customer)) {
            return $customer;
        }

        if ($holdReasons = static::erasureHoldReasons($customer, $fraudNote)) {
            return $this->holdErasure($customer, $reason, $holdReasons, $fraudNote);
        }

        SendAccountErasedEmail::run($customer);

        $webUserIds = $customer->webUsers()->withTrashed()->pluck('id');

        DB::transaction(fn () => Customer::withoutAuditing(function () use ($customer, $keepCompanyName, $webUserIds) {
            $this->anonymiseAddresses($customer);
            $this->anonymiseCustomerRecord($customer, $keepCompanyName);
            $this->anonymiseCustomerClients($customer);
            $this->anonymiseWebUsers($customer);
            $this->anonymiseChats($webUserIds->all());
            $this->anonymiseShoppingAssistantConversations($customer);
            $this->anonymiseDispatchedEmailRecipients($customer, $webUserIds->all());
            $this->unsubscribeFromAllComms($customer);
            $this->scrubEarlierAudits($customer, $webUserIds->all());

            $customer->delete();
        }, globally: true));

        $customer->unsearchable();
        $this->writeErasureAudit($customer, $reason, $keepCompanyName);

        ShopHydrateCustomers::dispatch($customer->shop);

        return $customer;
    }

    public static function isAnonymised(Customer $customer): bool
    {
        return $customer->trashed() && Arr::get($customer->data, 'deleted.cause') === self::DELETED_CAUSE;
    }

    public static function isErasureHeld(Customer $customer): bool
    {
        return $customer->trashed() && Arr::get($customer->data, 'deleted.cause') === self::HELD_DELETED_CAUSE;
    }

    /**
     * Erasure gives way to fraud prevention and to money still owed (GDPR art. 17(3)(e)): such a
     * customer is closed but their identity is kept, so it can be checked or claimed later (HELP-4652).
     *
     * @return array<int, string>
     */
    public static function erasureHoldReasons(Customer $customer, ?string $fraudNote = null): array
    {
        $holdReasons = [];
        if (filled($fraudNote)) {
            $holdReasons[] = 'suspected_fraud';
        }
        if ((float)$customer->balance < 0 || static::unpaidInvoicesQuery($customer)->exists()) {
            $holdReasons[] = 'owes_money';
        }

        return $holdReasons;
    }

    public static function unpaidInvoicesQuery(Customer $customer): HasMany
    {
        return $customer->invoices()
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->where('pay_status', InvoicePayStatusEnum::UNPAID)
            ->where('in_process', false)
            ->whereColumn('total_amount', '>', 'payment_amount')
            ->where('date', '>=', now()->subYear());
    }

    /** @param array<int, string> $holdReasons */
    private function holdErasure(Customer $customer, string $reason, array $holdReasons, ?string $fraudNote): Customer
    {
        DB::transaction(function () use ($customer) {
            $customer->webUsers()->get()->each(fn (WebUser $webUser) => $webUser->delete());
            $this->unsubscribeFromAllComms($customer);
            $customer->forceFill([
                'data' => array_merge((array)$customer->data, ['deleted' => ['cause' => self::HELD_DELETED_CAUSE, 'at' => now()->toIso8601String()]]),
            ])->saveQuietly();
            $customer->delete();
        });

        $customer->auditEvent     = self::HELD_AUDIT_EVENT;
        $customer->isCustomEvent  = true;
        $customer->auditCustomOld = [];
        $customer->auditCustomNew = [
            'reason'          => $reason,
            'hold_reasons'    => $holdReasons,
            'fraud_note'      => $fraudNote,
            'balance'         => $customer->balance,
            'unpaid_invoices' => static::unpaidInvoicesQuery($customer)->count(),
            'held_at'         => now()->toIso8601String(),
        ];
        Event::dispatch(new AuditCustom($customer));

        ShopHydrateCustomers::dispatch($customer->shop);

        return $customer;
    }

    private function anonymiseCustomerRecord(Customer $customer, bool $keepCompanyName): void
    {
        $location    = $customer->location;
        $location[2] = '';

        if (!$keepCompanyName) {
            $customer->taxNumber()->delete();
        }

        $customer->forceFill([
            'name'                     => 'Anonymised '.$customer->reference,
            'contact_name'             => null,
            'company_name'             => $keepCompanyName ? $customer->company_name : null,
            'fiscal_name'              => $keepCompanyName ? $customer->fiscal_name : null,
            'email'                    => null,
            'phone'                    => null,
            'marketing_whatsapp_phone' => null,
            'identity_document_type'   => null,
            'identity_document_number' => null,
            'contact_website'          => null,
            'internal_notes'           => null,
            'warehouse_internal_notes' => null,
            'warehouse_public_notes'   => null,
            'rejected_notes'           => null,
            'accounting_reference'     => null,
            'contact_name_components'  => [],
            'location'                 => $location,
            'address_id'               => null,
            'delivery_address_id'      => null,
            'migration_data'           => [],
            'data'                     => ['deleted' => ['cause' => self::DELETED_CAUSE, 'at' => now()->toIso8601String()]],
        ]);
        $customer->syncSearchableText();
        $customer->saveQuietly();
    }

    private function anonymiseAddresses(Model $model): void
    {
        foreach ($model->addresses as $address) {
            $isSharedWithOtherModels = DB::table('model_has_addresses')
                ->where('address_id', $address->id)
                ->where(fn ($query) => $query->where('model_type', '!=', $model->getMorphClass())->orWhere('model_id', '!=', $model->id))
                ->exists();

            if (!$isSharedWithOtherModels) {
                $this->wipeAddress($address);
            }
        }
        $model->addresses()->detach();
    }

    private function wipeAddress(Address $address): void
    {
        $address->forceFill([
            'address_line_1'      => null,
            'address_line_2'      => null,
            'sorting_code'        => null,
            'postal_code'         => null,
            'dependent_locality'  => null,
            'locality'            => null,
            'administrative_area' => null,
            'latitude'            => null,
            'longitude'           => null,
            'geocoding_metadata'  => null,
            'checksum'            => null,
        ])->saveQuietly();
    }

    private function anonymiseCustomerClients(Customer $customer): void
    {
        foreach ($customer->clients()->withTrashed()->get() as $client) {
            $this->anonymiseAddresses($client);
            $client->forceFill([
                'name'                => 'Anonymised '.$client->reference,
                'contact_name'        => null,
                'company_name'        => null,
                'email'               => null,
                'phone'               => null,
                'address_id'          => null,
                'delivery_address_id' => null,
                'location'            => [],
            ])->saveQuietly();
        }
    }

    private function anonymiseWebUsers(Customer $customer): void
    {
        foreach ($customer->webUsers()->withTrashed()->get() as $webUser) {
            $webUser->tokens()->delete();
            DB::table('web_user_password_resets')->where('web_user_id', $webUser->id)->delete();
            DB::table('web_user_logins')->where('web_user_id', $webUser->id)->update(['ip_address' => null]);

            $webUser->forceFill([
                'username'          => 'gdpr-'.$webUser->id,
                'email'             => 'anonymised-'.$webUser->id.'@example.invalid',
                'contact_name'      => null,
                'about'             => null,
                'password'          => null,
                'remember_token'    => null,
                'email_verified_at' => null,
                'data'              => [],
            ])->saveQuietly();

            if (!$webUser->trashed()) {
                $webUser->delete();
            }
        }
    }

    private function anonymiseChats(array $webUserIds): void
    {
        if (!$webUserIds) {
            return;
        }

        $sessionIds = ChatSession::whereIn('web_user_id', $webUserIds)->pluck('id');
        if ($sessionIds->isEmpty()) {
            return;
        }

        $messages = ChatMessage::withTrashed()
            ->whereIn('chat_session_id', $sessionIds)
            ->where('sender_type', ChatSenderTypeEnum::USER);

        ChatMessageTranslation::whereIn('chat_message_id', (clone $messages)->pluck('id'))
            ->update(['translated_text' => self::ERASED]);

        $messages->update([
            'message_text'  => self::ERASED,
            'original_text' => null,
            'metadata'      => null,
        ]);

        ChatSession::whereIn('id', $sessionIds)->update(['metadata' => null, 'guest_identifier' => null]);
    }

    /**
     * Wipes what the customer wrote and was told by the shopping assistant, and the questions it
     * refused. Counters, tokens, cost and the product ids of the events stay for reporting.
     */
    private function anonymiseShoppingAssistantConversations(Customer $customer): void
    {
        DB::table('shopping_assistant_refusals')->where('customer_id', $customer->id)->update(['question' => null]);

        $conversationIds = ShoppingAssistantConversation::where('customer_id', $customer->id)->pluck('id');
        if ($conversationIds->isEmpty()) {
            return;
        }

        ShoppingAssistantMessage::whereIn('shopping_assistant_conversation_id', $conversationIds)
            ->update(['body' => null, 'tool_calls' => null, 'follow_ups' => null]);
        ShoppingAssistantEvent::whereIn('shopping_assistant_conversation_id', $conversationIds)
            ->update(['reason' => null]);
    }

    private function anonymiseDispatchedEmailRecipients(Customer $customer, array $webUserIds): void
    {
        $dispatchedEmailIds = $customer->dispatchedEmails()->pluck('dispatched_emails.id');

        foreach (['mailshot_recipients', 'email_bulk_run_recipients'] as $table) {
            DB::table($table)->whereIn('dispatched_email_id', $dispatchedEmailIds)->update(['recipient_name' => null]);
        }

        $emailAddressIds = DB::table('dispatched_emails')->whereIn('id', $dispatchedEmailIds)->whereNotNull('email_address_id')->distinct()->pluck('email_address_id');
        foreach (EmailAddress::whereIn('id', $emailAddressIds)->get() as $emailAddress) {
            if ($this->isEmailStillInUse($emailAddress->email, $customer)) {
                continue;
            }
            $emailAddress->updateQuietly(['email' => 'anonymised-'.$customer->id.'-'.$emailAddress->id.'@example.invalid']);
        }
    }

    private function isEmailStillInUse(string $email, Customer $customer): bool
    {
        return Customer::where('email', $email)->where('id', '!=', $customer->id)->exists()
            || WebUser::where('email', $email)->exists()
            || Prospect::where('email', $email)->exists();
    }

    private function unsubscribeFromAllComms(Customer $customer): void
    {
        $comms = $customer->comms;
        if (!$comms) {
            return;
        }

        $unsubscribed = [];
        foreach (Arr::where($comms->getAttributes(), fn ($value, string $key) => str_starts_with($key, 'is_subscribed_to_')) as $column => $value) {
            $channel                                        = substr($column, strlen('is_subscribed_to_'));
            $unsubscribed[$column]                          = false;
            $unsubscribed[$channel.'_unsubscribed_at']      = now();
            $unsubscribed[$channel.'_unsubscribed_origin_type'] = 'Customer';
        }
        $comms->forceFill($unsubscribed)->saveQuietly();

        WhatsappSubscriber::withTrashed()
            ->where('parent_type', 'Customer')
            ->where('parent_id', $customer->id)
            ->update(['consented_at' => null, 'consent' => null]);

        SyncCustomerWhatsappSubscriber::run($customer);
    }

    private function scrubEarlierAudits(Customer $customer, array $webUserIds): void
    {
        Audit::where(function ($query) use ($customer, $webUserIds) {
            $query->where(fn ($q) => $q->where('auditable_type', 'Customer')->where('auditable_id', $customer->id))
                ->orWhere(fn ($q) => $q->where('auditable_type', 'WebUser')->whereIn('auditable_id', $webUserIds))
                ->orWhere(fn ($q) => $q->where('auditable_type', 'CustomerClient')->whereIn('auditable_id', $customer->clients()->withTrashed()->pluck('id')))
                ->orWhere(fn ($q) => $q->where('user_type', 'WebUser')->whereIn('user_id', $webUserIds));
        })->update([
            'old_values' => DB::raw("'{}'::jsonb"),
            'new_values' => DB::raw("'{}'::jsonb"),
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }

    private function writeErasureAudit(Customer $customer, string $reason, bool $keepCompanyName): void
    {
        $customer->auditEvent     = self::AUDIT_EVENT;
        $customer->isCustomEvent  = true;
        $customer->auditCustomOld = [];
        $customer->auditCustomNew = [
            'reason'            => $reason,
            'keep_company_name' => $keepCompanyName,
            'anonymised_at'     => now()->toIso8601String(),
        ];
        Event::dispatch(new AuditCustom($customer));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return static::canBeAnonymisedBy($request->user(), $this->customer);
    }

    public static function confirmationText(Customer $customer): string
    {
        return DeleteCustomer::canBeDeleted($customer) ? $customer->reference : $customer->reference.' with orders';
    }

    public static function canBeAnonymisedBy(User $user, Customer $customer): bool
    {
        if ($user->authTo("supervisor-crm.$customer->shop_id")) {
            return true;
        }

        return $user->authTo("crm.$customer->shop_id.edit") && DeleteCustomer::canBeDeleted($customer);
    }

    public function rules(): array
    {
        return [
            'reason'            => ['required', 'string', 'max:1000'],
            'reference'         => ['required', 'string', 'in:'.static::confirmationText($this->customer)],
            'keep_company_name' => ['sometimes', 'boolean'],
            'fraud_note'        => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData['reason'], (bool)Arr::get($this->validatedData, 'keep_company_name', false), Arr::get($this->validatedData, 'fraud_note'));
    }

    public function htmlResponse(Customer $customer): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.index', [
            $customer->organisation->slug,
            $customer->shop->slug,
        ]);
    }

    public function action(Customer $customer, string $reason, bool $keepCompanyName = false, ?string $fraudNote = null): Customer
    {
        $this->asAction = true;
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, [
            'reason'            => $reason,
            'reference'         => static::confirmationText($customer),
            'keep_company_name' => $keepCompanyName,
        ]);

        return $this->handle($customer, $reason, $keepCompanyName, $fraudNote);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customer = Customer::withTrashed()->where('slug', $command->argument('slug'))->first();
        if (!$customer) {
            $command->error('Customer not found');

            return 1;
        }
        if (static::isAnonymised($customer) || static::isErasureHeld($customer)) {
            $command->info('Customer '.$customer->reference.' is already anonymised or closed');

            return 0;
        }

        $reason = $command->option('reason') ?: 'GDPR erasure request';

        if (!$command->option('no-interaction') && !$command->confirm("Anonymise customer {$customer->reference} ({$customer->name}, {$customer->shop->slug})? This can not be undone.")) {
            return 1;
        }

        $this->handle($customer, $reason, (bool)$command->option('keep-company-name'), $command->option('fraud-note'));

        $command->info('Customer '.$customer->reference.(static::isErasureHeld($customer) ? ' closed, identity kept (suspected fraud or money owed)' : ' anonymised and soft deleted'));

        return 0;
    }
}
