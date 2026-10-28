<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 11:56:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;

class PrepareNewsletterRecipients
{
    use AsAction;

    public string $jobQueue = 'urgent';
    protected int $countRecipients = 0;

    public function tags(): array
    {
        return ['send_newsletter'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $chunkSize = 50;

        // NOTE: Ensure no second wave exists when the parent mailshot has the second wave disabled
        if ($mailshot->secondWave()->exists() && !$mailshot->is_second_wave_enabled) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }


        $baseQuery = $this->audienceQuery($mailshot);

        $this->countRecipients += $this->sendToSuppliers($mailshot, $chunkSize);

        if ($mailshot->abTestVariant()->exists()) {
            PrepareMailshotAbTestRecipients::run($mailshot, $baseQuery);

            return;
        }

        $customers = $baseQuery->select('customers.id', 'customers.email')->orderBy('customers.id')->get();

        foreach ($customers->chunk($chunkSize) as $customersChunk) {
            $customerIds = [];
            foreach ($customersChunk as $customer) {
                if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                    $customerIds[] = $customer->id;
                }
            }

            if (!empty($customerIds)) {
                ProcessSendMailshot::dispatch($mailshot->id, $customerIds);
                $this->countRecipients += count($customerIds);
            }
        }

        $mailshot->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $this->countRecipients,
        ]);
        UpdateMailshotRecipientsStoredAt::run($mailshot);
    }

    /**
     * Shops set to send the newsletter to suppliers also send it to every email of their group's active suppliers, as
     * sent to that supplier contact so it can unsubscribe. They get the newsletter as it is, outside any A/B test or
     * second wave, and an email already subscribed as a customer of the shop gets it once, as the customer.
     */
    public function sendToSuppliers(Mailshot $mailshot, int $chunkSize): int
    {
        if (!$mailshot->shop?->sendsNewsletterToSuppliers()) {
            return 0;
        }

        $contactIds = $this->supplierAudienceQuery($mailshot)
            ->orderBy('supplier_portal_contacts.id')
            ->get(['supplier_portal_contacts.id', 'supplier_portal_contacts.email'])
            ->filter(fn ($contact) => filter_var($contact->email, FILTER_VALIDATE_EMAIL))
            ->pluck('id');

        foreach ($contactIds->chunk($chunkSize) as $chunk) {
            ProcessSendMailshot::dispatch($mailshot->id, [], $chunk->values()->all());
        }

        return $contactIds->count();
    }

    public function supplierAudienceQuery(Mailshot $mailshot): Builder
    {
        return DB::table('supplier_portal_contacts')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_portal_contacts.supplier_id')
            ->where('suppliers.group_id', $mailshot->group_id)
            ->where('suppliers.status', true)
            ->whereNull('suppliers.deleted_at')
            ->whereNull('supplier_portal_contacts.revoked_at')
            ->where('supplier_portal_contacts.is_subscribed_to_newsletter', true)
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('customers')
                ->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
                ->where('customers.shop_id', $mailshot->shop_id)
                ->where('customer_comms.is_subscribed_to_newsletter', true)
                ->whereNull('customers.deleted_at')
                ->whereRaw('lower(customers.email) = lower(supplier_portal_contacts.email)'));
    }

    public function audienceQuery(Mailshot $mailshot): Builder
    {
        return DB::table('customers')
            ->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
            ->where('customers.shop_id', $mailshot->shop_id)
            ->where('customer_comms.is_subscribed_to_newsletter', true)
            ->whereNotNull('customers.email')
            ->whereNull('customers.deleted_at');
    }

    public string $commandSignature = 'mailshot:send {mailshot}';

    public function asCommand(Command $command): int
    {
        try {
            $mailshot = Mailshot::where('slug', $command->argument('mailshot'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($mailshot);

        return 0;
    }
}
