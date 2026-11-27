<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Accounting\EInvoice\GetCustomersDicStatus;
use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\Retina\CRM\ShowRetinaCompanyDetails;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * One-off service email (HELP-4976), in Slovak and whether or not the customer takes marketing emails: asks every Slovak
 * business customer of an organisation whose DIČ the register has not confirmed to check the company details we hold
 * before e-invoicing starts, with a signed link to the page where they fill in what is missing. Each email goes from the
 * customer's own shop. A customer emailed in the last --resend-after-days days is skipped, so a rerun reaches only those
 * still missing details.
 */
class SendCompanyDetailsRequest
{
    use AsAction;
    use WithSendBulkEmails;

    public string $commandSignature = 'customers:request-company-details {customer? : send to this customer id only} {--sample-to= : with a customer, send its email to this address instead} {--organisation=sk : organisation slug} {--dry-run : only count, send nothing} {--resend-after-days=14}';

    public string $jobQueue = 'low-priority';

    public int $jobTries = 1;

    public static function recipients(Organisation $organisation, int $resendAfterDays): Builder
    {
        return GetCustomersDicStatus::incompleteInOrganisation($organisation)
            ->where('customers.status', CustomerStatusEnum::APPROVED)
            ->whereNotNull('customers.email')
            ->where('customers.email', '<>', '')
            ->whereExists(fn (\Illuminate\Database\Query\Builder $website) => $website->selectRaw('1')->from('websites')->whereColumn('websites.shop_id', 'customers.shop_id'))
            ->where(fn (Builder $query) => $query->whereRaw("customers.data->>'company_details_requested_at' is null")
                ->orWhereRaw("(customers.data->>'company_details_requested_at')::timestamptz < ?", [now()->subDays($resendAfterDays)]));
    }

    public static function template(Shop $shop): ?string
    {
        /** @var Outbox $outbox */
        $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::COMPANY_DETAILS_REQUEST)->first();
        if (!$outbox || $outbox->state != OutboxStateEnum::ACTIVE) {
            return null;
        }

        return Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
    }

    public function handle(int|Customer $customer, ?string $sampleTo = null): ?DispatchedEmail
    {
        $customer = $customer instanceof Customer ? $customer : Customer::find($customer);
        if (!$customer?->email || !$customer->shop->website) {
            return null;
        }
        if (!$sampleTo && data_get($customer->data, 'company_details_requested_at') && now()->subDay()->lt(data_get($customer->data, 'company_details_requested_at'))) {
            return null;
        }

        $emailHtmlBody = self::template($customer->shop);
        if (!$emailHtmlBody) {
            return null;
        }
        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::COMPANY_DETAILS_REQUEST)->first();

        $previousLocale = app()->getLocale();
        app()->setLocale('sk');

        try {
            $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $customer, [
                'outbox_id'     => $outbox->id,
                'email_address' => $sampleTo ?? $customer->email,
            ]);

            $dispatchedEmail = $this->sendEmailWithMergeTags(
                $dispatchedEmail->refresh(),
                $outbox->emailOngoingRun->sender(),
                __('Please check your company details for e-invoices'),
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name' => $customer->name,
                    'shop_name'     => $customer->shop->name,
                    'email_body'    => $this->generateBodyHtml($customer),
                ],
                senderName: $outbox->emailOngoingRun->senderName(),
            );

            if (!$sampleTo) {
                Customer::where('id', $customer->id)->update(['data->company_details_requested_at' => now()->toIso8601String()]);
            }

            return $dispatchedEmail;
        } catch (Throwable $exception) {
            Sentry::captureException($exception);

            return null;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    public function generateBodyHtml(Customer $customer): string
    {
        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';
        $label     = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 13px; color: #666; padding: 4px 12px 4px 0; border-bottom: 1px solid #eee; vertical-align: top; white-space: nowrap;"';
        $value     = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 13px; color: #333; padding: 4px 0; border-bottom: 1px solid #eee; vertical-align: top;"';
        $missing   = '<span style="color: #c0392b;">'.__('missing').'</span>';
        $url       = e(ShowRetinaCompanyDetails::url($customer));
        $held      = ShowRetinaCompanyDetails::heldDetails($customer);

        $rows = collect([
            __('Company')  => $customer->company_name ?: $customer->name,
            __('Address')  => $customer->address?->formatted_address,
            'IČO'          => $held['ico'],
            'DIČ'          => $held['dic'],
            'IČ DPH'       => $held['vat_number'],
        ])->map(fn (?string $text, string $name) => '<tr><td '.$label.'>'.e($name).'</td><td '.$value.'>'.($text ? nl2br(e($text)) : $missing).'</td></tr>')->implode('');

        return '<p '.$paragraph.'>'.__('Hello :name,', ['name' => e($customer->contact_name ?: $customer->name)]).'</p>'
            .'<p '.$paragraph.'>'.__('From 1 January 2027 invoices in Slovakia are sent as electronic invoices. So that our invoices reach you correctly, we need your company details to be complete.').'</p>'
            .'<p '.$paragraph.'>'.__('These are the details we hold for you:').'</p>'
            .'<table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;">'.$rows.'</table>'
            .'<p style="margin: 0 0 20px; text-align: center;"><a ses:no-track href="'.$url.'" style="display: inline-block; background-color: #333; color: #fff; font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; font-weight: bold; text-decoration: none; padding: 12px 24px; border-radius: 4px;">'.__('Check and complete my details').'</a></p>'
            .'<p '.$paragraph.'>'.__('If the button does not work, copy this link into your browser:').'<br><span style="word-break: break-all; font-size: 12px; color: #666;">'.$url.'</span></p>'
            .'<p '.$paragraph.'>'.__('Leave IČ DPH empty if you are not a VAT payer.').' '.__('We will never ask for these details by reply email, only on our website.').'</p>';
    }

    public function asCommand(Command $command): int
    {
        if ($customerId = $command->argument('customer')) {
            $dispatchedEmail = $this->handle((int)$customerId, $command->option('sample-to') ?: null);
            $command->info($dispatchedEmail ? 'Sent to '.$dispatchedEmail->email_address : 'Not sent: no email, website or live template for this customer\'s shop.');

            return $dispatchedEmail ? 0 : 1;
        }

        $organisation = Organisation::where('slug', $command->option('organisation'))->firstOrFail();
        $recipients   = self::recipients($organisation, (int)$command->option('resend-after-days'));

        $perShop = (clone $recipients)->join('shops', 'shops.id', '=', 'customers.shop_id')
            ->selectRaw('shops.id, count(*) as total')->groupBy('shops.id')->orderByDesc('total')->pluck('total', 'id');
        $shops   = Shop::whereIn('id', $perShop->keys())->get()->keyBy('id');
        $ready   = $shops->map(fn (Shop $shop) => self::template($shop) !== null);

        $command->table(['Shop', 'Customers', 'Email template'], $perShop->map(fn ($total, $shopId) => [$shops[$shopId]->slug, $total, $ready[$shopId] ? 'ready' : 'MISSING'])->values()->all());
        $command->info('Total: '.$perShop->sum());

        if ($ready->contains(false)) {
            $command->error('Some shops have no live template: run group:seed_email_templates first.');

            return 1;
        }

        if ($command->option('dry-run')) {
            return 0;
        }

        $recipients->select('customers.id')->orderBy('customers.id')->chunkById(500, function ($customers) {
            foreach ($customers as $customer) {
                static::dispatch($customer->id);
            }
        }, 'customers.id', 'id');

        $command->info('Queued.');

        return 0;
    }
}
