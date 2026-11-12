<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer told the AI shopping assistant they plan an order of at least the organisation's
 * big order threshold (organisation settings, sales_leads.threshold_eur, else
 * services.shopping_assistant.sales_lead_threshold_eur; in euros, compared in the customer's
 * currency), whether or not they have agreed to a call yet: a planned big order is saved either way,
 * so it is never lost. 2,000 EUR was chosen on 12 Nov 2026 from 12 months of B2B orders:
 * about the top 1% of orders in AW, SK and ES, some 750 a year in the group; Aroma's big customers
 * order more, so it may set its own. The lead becomes a high priority task of that organisation's customer services
 * team, linked to the customer, so every member is told in aiku; the organisation's sales leads
 * managers (organisation settings) are on the task and are told on the channels they chose for
 * sales leads, which default to all of them; the organisation's sales leads Slack channel gets a
 * post. A second request while the customer's lead is still open is added to that task.
 *
 * Who is told: the sales leads managers set on the organisation, else its customer services
 * supervisors (job positions ending in -m), else its org admins (Raul, 12 Nov 2026: on 12 Nov only
 * AW had supervisors, so SK, ES and Aroma leads reach their 18-21 org admins until a manager is
 * set). Calls are offered in every organisation with at least one of them; with nobody at all the
 * assistant neither offers a call nor saves a lead. The alert is queued (AlertShoppingAssistantSalesLead), so a mail or Slack
 * failure never touches the customer's answer. RemindShoppingAssistantSalesLeads repeats it while
 * nobody takes the task.
 */
class StoreShoppingAssistantSalesLead
{
    use AsAction;

    public const string TASK_KIND = 'shopping_assistant_sales_lead';

    public const string DEPARTMENT = 'customer-services';

    /**
     * @return array{status: 'created'|'updated'|'below_threshold'|'not_offered', task: ?StaffTask, threshold: float, currency: string}
     */
    public function handle(ShoppingAssistantConversation $conversation, float $amount, ?string $currencyCode, bool $agreedToCall, ?string $phone = null, ?string $bestTime = null, ?string $details = null): array
    {
        $customer  = $conversation->customer;
        $shop      = $customer->shop;
        $currency  = ($currencyCode ? Currency::where('code', strtoupper(trim($currencyCode)))->first() : null) ?? $shop->currency;
        $threshold = $this->threshold($customer->organisation, $currency);

        if ($amount < $threshold) {
            return ['status' => 'below_threshold', 'task' => null, 'threshold' => $threshold, 'currency' => $currency->code];
        }

        $assistant = GetAikuAssistant::run($customer->group_id);
        if (!self::offersCalls($customer->organisation, $assistant)) {
            return ['status' => 'not_offered', 'task' => null, 'threshold' => $threshold, 'currency' => $currency->code];
        }

        $amountText = Number::currency($amount, $currency->code);
        $phone      = $this->clean($phone) ?? $this->clean($customer->phone);
        $bestTime   = $this->clean($bestTime);
        $details    = $this->clean($details);

        $open = self::openLeadOf($customer);
        if ($open) {
            $agreedNow = $agreedToCall || (bool) data_get($open->data, 'agreed_to_call');
            $phone     ??= data_get($open->data, 'phone');
            $bestTime  ??= data_get($open->data, 'best_time');
            $details   ??= data_get($open->data, 'details');
            $task      = UpdateStaffTask::run($open, $assistant, [
                'description' => $this->description($conversation, $customer, $amountText, $agreedNow, $phone, $bestTime, $details),
                'note' => __('Update from the AI shopping assistant: about :amount, agreed to a call: :agreed, phone :phone, best time :time. :details', [
                    'amount'  => $amountText,
                    'agreed'  => $agreedToCall ? __('yes') : __('not yet'),
                    'phone'   => $phone ?? '—',
                    'time'    => $bestTime ?? '—',
                    'details' => $details ?? '',
                ]),
            ]);
            $task->update(['data' => [...$task->data, 'agreed_to_call' => $agreedNow, 'phone' => $phone, 'best_time' => $bestTime, 'details' => $details, 'amount' => $amount, 'currency' => $currency->code]]);

            return ['status' => 'updated', 'task' => $task, 'threshold' => $threshold, 'currency' => $currency->code];
        }

        $organisation = $customer->organisation;
        $managers     = self::managersOf($organisation, $assistant);

        $task = StoreStaffTask::run($assistant, [
            'subject'          => __('Call :customer about a big order (about :amount)', ['customer' => $customer->name, 'amount' => $amountText]),
            'description'      => $this->description($conversation, $customer, $amountText, $agreedToCall, $phone, $bestTime, $details),
            'department'       => self::DEPARTMENT,
            'collaborator_ids' => $managers->pluck('id')->all(),
            'priority'         => ChatPriorityEnum::HIGH->value,
            'due_at'           => now()->toDateString(),
            'model_type'       => 'Customer',
            'model_id'         => $customer->id,
            'data'             => [
                'kind'              => self::TASK_KIND,
                'organisation_id'   => $customer->organisation_id,
                'shop_id'           => $customer->shop_id,
                'website_id'        => $conversation->website_id,
                'conversation_id'   => $conversation->id,
                'conversation_ulid' => $conversation->ulid,
                'customer_name'     => $customer->name,
                'amount'            => $amount,
                'currency'          => $currency->code,
                'phone'             => $phone,
                'best_time'         => $bestTime,
                'agreed_to_call'    => $agreedToCall,
                'details'           => $details,
            ],
        ]);

        AlertShoppingAssistantSalesLead::dispatch($task, false);

        return ['status' => 'created', 'task' => $task, 'threshold' => $threshold, 'currency' => $currency->code];
    }

    /**
     * The threshold in the given currency, rounded to 100. With no exchange rate known the euro
     * figure is used as it is, so a missing rate neither drops a big order nor turns every amount
     * into a lead.
     */
    public function threshold(Organisation $organisation, Currency $currency): float
    {
        $euro = Currency::where('code', 'EUR')->first();
        $rate = $euro ? GetCurrencyExchange::run($euro, $currency) : null;

        return round(self::thresholdEur($organisation) * ($rate ?: 1), -2);
    }

    public static function thresholdEur(Organisation $organisation): float
    {
        $set = Arr::get($organisation->settings, 'sales_leads.threshold_eur');

        return is_numeric($set) && (float) $set > 0 ? (float) $set : (float) config('services.shopping_assistant.sales_lead_threshold_eur');
    }

    public static function openLeadOf(Customer $customer): ?StaffTask
    {
        return StaffTask::open()
            ->where('model_type', 'Customer')
            ->where('model_id', $customer->id)
            ->where('data->kind', self::TASK_KIND)
            ->latest('id')
            ->first();
    }

    public static function offersCalls(Organisation $organisation, User $assistant): bool
    {
        return self::managersOf($organisation, $assistant)->isNotEmpty();
    }

    /**
     * @return Collection<int, User>
     */
    public static function managersOf(Organisation $organisation, User $assistant): Collection
    {
        $chosen = self::chosenManagersOf($organisation);
        if ($chosen->isNotEmpty()) {
            return $chosen;
        }

        $supervisors = StaffTask::departmentSupervisors($assistant, self::DEPARTMENT, $organisation->id);
        if ($supervisors->isNotEmpty()) {
            return $supervisors;
        }

        return User::where('group_id', $organisation->group_id)
            ->where('status', true)
            ->whereIn('id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', 'User')
                ->where('roles.name', RolesEnum::ORG_ADMIN->value.'-'.$organisation->id)
                ->select('model_has_roles.model_id'))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private static function chosenManagersOf(Organisation $organisation): Collection
    {
        return User::whereIn('id', Arr::get($organisation->settings, 'sales_leads.manager_user_ids', []))->where('status', true)->get();
    }

    private function description(ShoppingAssistantConversation $conversation, Customer $customer, string $amountText, bool $agreedToCall, ?string $phone, ?string $bestTime, ?string $details): string
    {
        $website = $conversation->website;

        return implode("\n", array_filter([
            $agreedToCall
                ? __('A customer told the AI shopping assistant they plan a big order and asked for a call to discuss terms. Call them and record the outcome on this task.')
                : __('A customer told the AI shopping assistant they plan a big order. They were offered a call but have not said yes yet: contact them and record the outcome on this task.'),
            __('Customer: :name (:reference)', ['name' => $customer->name, 'reference' => $customer->reference]).' '.route('grp.org.shops.show.crm.customers.show', [$customer->organisation->slug, $customer->shop->slug, $customer->slug]),
            __('Planned order: about :amount', ['amount' => $amountText]),
            __('Phone: :phone', ['phone' => $phone ?? __('not given, check the customer record')]),
            $bestTime ? __('Best time to call: :time', ['time' => $bestTime]) : null,
            $details ? __('What they want: :details', ['details' => $details]) : null,
            $website ? __('Conversation on :website:', ['website' => $website->domain]).' '.route('grp.org.shops.show.web.analytics.shopping_assistant.conversations.show', [$customer->organisation->slug, $customer->shop->slug, $website->slug, $conversation->ulid]) : null,
        ]));
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 300);
    }
}
