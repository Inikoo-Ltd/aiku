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
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordEmailClickTouchpoint implements ShouldBeUnique
{
    use AsAction;

    public int $jobUniqueFor = 600;

    /* Mailshot click bursts belong with the rest of the SES event work, not on the default queue
       where they would compete with order processing. */
    public string $jobQueue = 'ses-analytics';

    /**
     * Newsletter campaign references are namespaced because `traffic_source_campaigns.reference` is
     * unique across the whole table, not per traffic source: a bare mailshot id would collide with a
     * UTM campaign that happens to use the same reference and blow up the click path on insert.
     */
    public const CAMPAIGN_REF_PREFIX = 'mailshot-';

    /** Same namespacing reason as above, for the automated emails that are not mailshots. */
    public const OUTBOX_CAMPAIGN_REF_PREFIX = 'outbox-';

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
    public function getJobUniqueId(Customer|Prospect $recipient, ?Carbon $occurredAt = null, ?Mailshot $mailshot = null, ?string $outboxCode = null): string
    {
        return $recipient->getMorphClass().'-'.$recipient->id.'-'.($mailshot?->id ?? $outboxCode ?? 'no-mailshot');
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
    public function handle(Customer|Prospect $recipient, ?Carbon $occurredAt = null, ?Mailshot $mailshot = null, ?string $outboxCode = null): void
    {
        $occurredAt = $occurredAt ?? now();

        $touches = ParseTrafficSourceTouches::run($recipient->traffic_sources);

        /* A mailshot is the newsletter channel; anything else we send that counts as marketing - a
           reorder reminder, an abandoned basket chase, a back-in-stock notice - is its own channel,
           with the outbox code as the campaign reference so each kind reports separately. Mixing
           them into newsletter would hide which of the two actually works. */
        $type = match (true) {
            (bool) $mailshot     => TrafficSourcesTypeEnum::fromMailshotType($mailshot->type?->value ?? $mailshot->type),
            $outboxCode !== null => TrafficSourcesTypeEnum::EMAIL_AUTOMATED,
            default              => TrafficSourcesTypeEnum::NEWSLETTER,
        };

        $campaignRef = match (true) {
            (bool) $mailshot        => self::CAMPAIGN_REF_PREFIX.$mailshot->id,
            $outboxCode !== null    => self::OUTBOX_CAMPAIGN_REF_PREFIX.$outboxCode,
            default                 => null,
        };

        $lastSameTouch = collect($touches)
            ->filter(fn (array $touch) => $touch['type'] === $type
                && $touch['campaign_ref'] === $campaignRef)
            ->last();

        if ($lastSameTouch && $lastSameTouch['timestamp']
            && Carbon::createFromTimestamp($lastSameTouch['timestamp'])->isSameDay($occurredAt)) {
            return;
        }

        if ($campaignRef) {
            $this->ensureCampaignExists(
                $recipient,
                $type,
                $campaignRef,
                $mailshot ? ($mailshot->subject ?? $mailshot->slug) : $this->outboxCampaignName($outboxCode)
            );
        }

        $abbr     = TrafficSourcesTypeEnum::abbr()[$type->value];
        $newTouch = $occurredAt->getTimestamp() . $abbr . ($campaignRef ?? '');

        /* Appended under a row lock: the device-cookie sync merges into the same column from
           another queue, and appending onto a stale read would let its write erase this click. */
        DB::transaction(function () use ($recipient, $newTouch) {
            $locked = $recipient->newQuery()->lockForUpdate()->find($recipient->getKey());

            $locked?->update([
                'traffic_sources' => $this->appendTouch($locked->traffic_sources, $newTouch),
            ]);
        });

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

    private function ensureCampaignExists(Customer|Prospect $recipient, TrafficSourcesTypeEnum $type, string $campaignRef, string $name): void
    {
        /** @var TrafficSource|null $trafficSource */
        $trafficSource = TrafficSource::where('shop_id', $recipient->shop_id)
            ->where('type', $type->value)
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
                'name' => $name,
                'type' => $type->value,
            ]
        );
    }

    /** "reorder_reminder_2nd" reads as "Reorder Reminder 2nd" in the campaign breakdown. */
    private function outboxCampaignName(string $outboxCode): string
    {
        return ucwords(str_replace('_', ' ', $outboxCode));
    }
}
