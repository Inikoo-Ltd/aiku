<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 13 Oct 2026 11:31:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\Hydrators;

use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MailshotHydratePurchases implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Mailshot $mailshot): string
    {
        return $mailshot->id;
    }

    public function handle(Mailshot $mailshot): void
    {
        $mailshot->stats->update([
            'number_purchases' => Arr::get($mailshot->stats->archived_dispatched_emails, 'number_purchases') ?? $this->purchasedRecipients($mailshot)->count(),
        ]);
    }

    /**
     * Customers this mailshot went to who submitted an order on the website from an email visit, between
     * the moment it started sending and the end of the shop's mailshot tracking window, or $until when given.
     * Once the archiver has banked the total the recipients are gone, so the banked value is final.
     */
    public function purchasedRecipients(Mailshot $mailshot, ?Carbon $until = null): Builder
    {
        $trackingEndsAt = $until ?? $mailshot->start_sending_at?->copy()->addDays(Arr::get($mailshot->shop->settings, 'mailshot_tracking.after_days', 30));

        return DB::table('mailshot_recipients')
            ->where('mailshot_recipients.mailshot_id', $mailshot->id)
            ->where('mailshot_recipients.recipient_type', 'Customer')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('orders')
                ->join('website_conversion_events', 'website_conversion_events.order_id', '=', 'orders.id')
                ->join('website_visitors', 'website_visitors.id', '=', 'website_conversion_events.website_visitor_id')
                ->whereColumn('orders.customer_id', 'mailshot_recipients.recipient_id')
                ->where('website_conversion_events.event_type', WebsiteConversionEventTypeEnum::PURCHASE->value)
                ->where('website_visitors.traffic_source_type', WebsiteVisitorChannelEnum::EMAIL_TYPE)
                ->whereBetween('orders.submitted_at', [$mailshot->start_sending_at, $trackingEndsAt]));
    }
}
