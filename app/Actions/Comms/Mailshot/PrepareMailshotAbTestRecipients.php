<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PrepareMailshotAbTestRecipients
{
    use AsAction;

    public function handle(Mailshot $mailshot, ?Builder $audienceQuery): void
    {
        $variant          = $mailshot->abTestVariant;
        $isVariantDelayed = $mailshot->ab_test_variant_delay_seconds > 0;

        if (!$isVariantDelayed) {
            $variant->update([
                'state'            => MailshotStateEnum::SENDING,
                'start_sending_at' => now(),
            ]);
        }

        $customerIds = $this->validCustomerIds($audienceQuery)->shuffle()->values()->all();

        $variantSize       = $this->variantGroupSize($mailshot, count($customerIds));
        $variantCustomers  = array_slice($customerIds, 0, $variantSize);
        $originalCustomers = array_slice($customerIds, $variantSize, $this->testGroupSize($mailshot, count($customerIds)) - $variantSize);

        $this->sendToCustomers($mailshot, $originalCustomers);

        if (!$isVariantDelayed) {
            $this->sendToCustomers($variant, $variantCustomers);
        }
    }

    /**
     * Version B sent hours after A: its half of the test group is drawn from whoever did not get A.
     */
    public function sendDelayedVariant(Mailshot $mailshot): void
    {
        $variant = $mailshot->abTestVariant;

        $variantSize   = $this->variantGroupSize($mailshot, $this->validCustomerIds($this->audienceQuery($mailshot))->count());
        $audienceQuery = $this->excludeRecipientsOf($this->audienceQuery($mailshot), [$mailshot->id]);

        $customerIds = $this->validCustomerIds($audienceQuery)->shuffle()->take($variantSize)->values()->all();

        $this->sendToCustomers($variant, $customerIds);

        if (!$customerIds) {
            UpdateMailshotSentState::run($variant->refresh());
        }
    }

    private function testGroupSize(Mailshot $mailshot, int $audienceSize): int
    {
        return (int)round($audienceSize * $mailshot->ab_test_size_percentage / 100);
    }

    private function variantGroupSize(Mailshot $mailshot, int $audienceSize): int
    {
        return intdiv($this->testGroupSize($mailshot, $audienceSize), 2);
    }

    /**
     * @param  array<int, int>  $mailshotIds
     */
    public function excludeRecipientsOf(?Builder $audienceQuery, array $mailshotIds): ?Builder
    {
        return $audienceQuery?->whereNotExists(function ($query) use ($mailshotIds) {
            $query->select(DB::raw(1))
                ->from('mailshot_recipients')
                ->whereColumn('mailshot_recipients.recipient_id', 'customers.id')
                ->where('mailshot_recipients.recipient_type', 'Customer')
                ->whereIn('mailshot_recipients.mailshot_id', $mailshotIds);
        });
    }

    public function audienceQuery(Mailshot $mailshot): ?Builder
    {
        if ($mailshot->type === MailshotTypeEnum::NEWSLETTER) {
            return PrepareNewsletterRecipients::make()->audienceQuery($mailshot);
        }

        return GetMailshotRecipientsQueryBuilder::make()->handle($mailshot);
    }

    /**
     * @return Collection<int, int>
     */
    public function validCustomerIds(?Builder $audienceQuery): Collection
    {
        if (!$audienceQuery) {
            return collect();
        }

        return $audienceQuery->select('customers.id', 'customers.email')
            ->orderBy('customers.id')
            ->get()
            ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
            ->pluck('id')
            ->unique();
    }

    /**
     * @param  array<int, int>  $customerIds
     */
    public function sendToCustomers(Mailshot $mailshot, array $customerIds, int $alreadyStoredRecipients = 0): void
    {
        foreach (array_chunk($customerIds, 50) as $customerIdsChunk) {
            ProcessSendMailshot::dispatch($mailshot->id, $customerIdsChunk);
        }

        $mailshot->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $alreadyStoredRecipients + count($customerIds),
        ]);
        UpdateMailshotRecipientsStoredAt::run($mailshot);
    }
}
