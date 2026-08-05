<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordEmailClickTouchpoint
{
    use AsAction;

    /**
     * Records a real (non-duplicate) email click as a `newsletter` marketing touch on the customer's
     * raw touch history, then rebuilds the customer's traffic-source attribution from that history.
     *
     * Deduplication: a click occurring within the same calendar day as the customer's most recent
     * `newsletter` touch is treated as a repeat click on an already-open email and is not recorded
     * again, so opening the same link/tab multiple times does not inflate the touch history.
     *
     * When a `Mailshot` is provided, the touch is stamped with a campaign reference derived from
     * the mailshot's id, matching the same `<traffic_source_id>+reference` campaign-matching used
     * for UTM-based touches, so newsletter revenue can be broken down per mailshot.
     */
    public function handle(Customer $customer, ?Carbon $occurredAt = null, ?Mailshot $mailshot = null): void
    {
        $occurredAt = $occurredAt ?? now();

        $touches = ParseTrafficSourceTouches::run($customer->traffic_sources);

        $campaignRef = $mailshot ? (string) $mailshot->id : null;

        $lastNewsletterTouch = collect($touches)
            ->filter(fn (array $touch) => $touch['type'] === TrafficSourcesTypeEnum::NEWSLETTER
                && $touch['campaign_ref'] === $campaignRef)
            ->last();

        if ($lastNewsletterTouch && $lastNewsletterTouch['timestamp']
            && Carbon::createFromTimestamp($lastNewsletterTouch['timestamp'])->isSameDay($occurredAt)) {
            return;
        }

        if ($mailshot) {
            $this->ensureCampaignExists($customer, $mailshot, $campaignRef);
        }

        $abbr     = TrafficSourcesTypeEnum::NEWSLETTER->abbr()[TrafficSourcesTypeEnum::NEWSLETTER->value];
        $newTouch = $occurredAt->getTimestamp() . $abbr . ($campaignRef ?? '');

        $customer->update([
            'traffic_sources' => $customer->traffic_sources
                ? $customer->traffic_sources . '|' . $newTouch
                : $newTouch,
        ]);

        RecalculateTrafficSourceAttribution::run($customer->fresh());
    }

    private function ensureCampaignExists(Customer $customer, Mailshot $mailshot, string $campaignRef): void
    {
        /** @var TrafficSource|null $trafficSource */
        $trafficSource = TrafficSource::where('shop_id', $customer->shop_id)
            ->where('type', TrafficSourcesTypeEnum::NEWSLETTER->value)
            ->first();

        if (!$trafficSource) {
            return;
        }

        TrafficSourceCampaign::firstOrCreate(
            [
                'traffic_source_id' => $trafficSource->id,
                'reference'         => $campaignRef,
            ],
            [
                'name' => $mailshot->subject ?? $mailshot->slug,
                'type' => TrafficSourcesTypeEnum::NEWSLETTER->value,
            ]
        );
    }
}
