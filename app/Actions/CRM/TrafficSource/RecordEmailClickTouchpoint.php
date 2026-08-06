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
use App\Models\CRM\Prospect;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordEmailClickTouchpoint implements ShouldBeUnique
{
    use AsAction;

    public int $jobUniqueFor = 600;

    /**
     * Newsletter campaign references are namespaced because `traffic_source_campaigns.reference` is
     * unique across the whole table, not per traffic source: a bare mailshot id would collide with a
     * UTM campaign that happens to use the same reference and blow up the click path on insert.
     */
    public const CAMPAIGN_REF_PREFIX = 'mailshot-';

    /**
     * Upper bound on how many touches a recipient's history keeps. The cookie path is already capped by
     * its own 3.9KB budget, but this column only ever grew: one touch per mailshot per day, forever.
     * The oldest touches are dropped and the very first is always kept, so first-touch attribution
     * survives trimming even though the middle of a long journey does not.
     *
     * ponytail: a flat cap, not a time-boxed lookback window. Swap it for a per-shop attribution window
     * when Phase 6 makes the window configurable.
     */
    public const MAX_TOUCHES = 50;

    /**
     * Bots, prefetchers and link scanners fire every link in an email at once, so the clicks of a single
     * recipient on a single mailshot arrive as a burst. Collapsing that burst into one job keeps the
     * concurrent workers from racing each other on the recipient's touch history, which is a
     * read-append-write. Different mailshots keep their own key so a genuine second click is never dropped.
     */
    public function getJobUniqueId(Customer|Prospect $recipient, ?Carbon $occurredAt = null, ?Mailshot $mailshot = null): string
    {
        return $recipient->getMorphClass().'-'.$recipient->id.'-'.($mailshot?->id ?? 'no-mailshot');
    }

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
     *
     * Accepts either a `Customer` or a `Prospect` recipient, since a mailshot may be dispatched to
     * either before a prospect has converted into a customer.
     */
    public function handle(Customer|Prospect $recipient, ?Carbon $occurredAt = null, ?Mailshot $mailshot = null): void
    {
        $occurredAt = $occurredAt ?? now();

        $touches = ParseTrafficSourceTouches::run($recipient->traffic_sources);

        $campaignRef = $mailshot ? self::CAMPAIGN_REF_PREFIX.$mailshot->id : null;

        $lastNewsletterTouch = collect($touches)
            ->filter(fn (array $touch) => $touch['type'] === TrafficSourcesTypeEnum::NEWSLETTER
                && $touch['campaign_ref'] === $campaignRef)
            ->last();

        if ($lastNewsletterTouch && $lastNewsletterTouch['timestamp']
            && Carbon::createFromTimestamp($lastNewsletterTouch['timestamp'])->isSameDay($occurredAt)) {
            return;
        }

        if ($mailshot) {
            $this->ensureCampaignExists($recipient, $mailshot, $campaignRef);
        }

        $abbr     = TrafficSourcesTypeEnum::NEWSLETTER->abbr()[TrafficSourcesTypeEnum::NEWSLETTER->value];
        $newTouch = $occurredAt->getTimestamp() . $abbr . ($campaignRef ?? '');

        $recipient->update([
            'traffic_sources' => $this->appendTouch($recipient->traffic_sources, $newTouch),
        ]);

        RecalculateTrafficSourceAttribution::run($recipient->fresh());
    }

    private function appendTouch(?string $history, string $newTouch): string
    {
        if (blank($history)) {
            return $newTouch;
        }

        $segments   = preg_split('/[|,]/', $history) ?: [];
        $segments[] = $newTouch;

        if (count($segments) <= self::MAX_TOUCHES) {
            return implode('|', $segments);
        }

        $first = array_shift($segments);

        return implode('|', array_merge([$first], array_slice($segments, -(self::MAX_TOUCHES - 1))));
    }

    private function ensureCampaignExists(Customer|Prospect $recipient, Mailshot $mailshot, string $campaignRef): void
    {
        /** @var TrafficSource|null $trafficSource */
        $trafficSource = TrafficSource::where('shop_id', $recipient->shop_id)
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
