<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 10:12:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomers;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageTranslation;
use App\Models\Chat\ChatSession;
use App\Models\Comms\EmailAddress;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Audit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class AnonymiseCustomer extends OrgAction
{
    public string $commandSignature = 'customer:anonymise {slug} {--reason=} {--keep-company-name}';

    public const string ERASED = '[erased]';

    public const string AUDIT_EVENT = 'anonymised';

    public const string DELETED_CAUSE = 'anonymised';

    private Customer $customer;

    public function handle(Customer $customer, string $reason, bool $keepCompanyName = false): Customer
    {
        if (static::isAnonymised($customer)) {
            return $customer;
        }

        $webUserIds = $customer->webUsers()->withTrashed()->pluck('id');

        DB::transaction(fn () => Customer::withoutAuditing(function () use ($customer, $keepCompanyName, $webUserIds) {
            $this->anonymiseAddresses($customer);
            $this->anonymiseCustomerRecord($customer, $keepCompanyName);
            $this->anonymiseCustomerClients($customer);
            $this->anonymiseWebUsers($customer);
            $this->anonymiseChats($webUserIds->all());
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

        return $request->user()->authTo("supervisor-crm.{$this->customer->shop_id}");
    }

    public function rules(): array
    {
        return [
            'reason'            => ['required', 'string', 'max:1000'],
            'reference'         => ['required', 'string', 'in:'.$this->customer->reference],
            'keep_company_name' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData['reason'], (bool)Arr::get($this->validatedData, 'keep_company_name', false));
    }

    public function htmlResponse(Customer $customer): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.index', [
            $customer->organisation->slug,
            $customer->shop->slug,
        ]);
    }

    public function action(Customer $customer, string $reason, bool $keepCompanyName = false): Customer
    {
        $this->asAction = true;
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, [
            'reason'            => $reason,
            'reference'         => $customer->reference,
            'keep_company_name' => $keepCompanyName,
        ]);

        return $this->handle($customer, $reason, $keepCompanyName);
    }

    public function asCommand(Command $command): int
    {
        $customer = Customer::withTrashed()->where('slug', $command->argument('slug'))->first();
        if (!$customer) {
            $command->error('Customer not found');

            return 1;
        }
        if (static::isAnonymised($customer)) {
            $command->info('Customer '.$customer->reference.' is already anonymised');

            return 0;
        }

        $reason = $command->option('reason') ?: 'GDPR erasure request';

        if (!$command->option('no-interaction') && !$command->confirm("Anonymise customer {$customer->reference} ({$customer->name}, {$customer->shop->slug})? This can not be undone.")) {
            return 1;
        }

        $this->handle($customer, $reason, (bool)$command->option('keep-company-name'));

        $command->info('Customer '.$customer->reference.' anonymised and soft deleted');

        return 0;
    }
}
