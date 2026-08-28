<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerJourney
{
    use AsObject;

    // ponytail: flat cap on rendered events; paginate the timeline if anyone needs to walk further back
    public const MAX_EVENTS = 200;

    /**
     * Builds the read-only marketing journey of a customer: every recorded traffic source touch and
     * every issued invoice, interleaved on a single time axis, plus the resulting attribution split.
     *
     * Only the most recent self::MAX_EVENTS events are returned; `omitted_events` reports how many
     * older ones were dropped so the timeline never silently presents itself as complete.
     *
     * @return array{events: array<int, array<string, mixed>>, omitted_events: int, attribution: array<int, array{label: string, share: float, campaign: string|null}>, attribution_window_days: int, currency_code: string}
     */
    public function handle(Customer $customer): array
    {
        $windowDays = $this->attributionWindowDays($customer);
        $labels     = TrafficSourcesTypeEnum::labels();

        $invoices = $customer->invoices()
            ->where('in_process', false)
            ->orderBy('date')
            ->get(['id', 'reference', 'date', 'net_amount']);

        $touches = collect(ParseTrafficSourceTouches::run($customer->traffic_sources))
            ->filter(fn (array $touch) => $touch['timestamp'] !== null)
            ->sortBy('timestamp')
            ->values();

        $campaignNames = $this->campaignNames($touches->pluck('campaign_ref')->filter()->unique()->all());

        $events = [];

        foreach ($touches as $index => $touch) {
            $date         = Carbon::createFromTimestamp($touch['timestamp']);
            $nextPurchase = $invoices->first(fn ($invoice) => $invoice->date->greaterThanOrEqualTo($date));
            $deadline     = $nextPurchase?->date ?? now();

            $events[] = [
                'id'            => 'touch-'.$index,
                'type'          => 'touch',
                'datetime'      => $date->toIso8601String(),
                'channel'       => $touch['type']->value,
                'label'         => $labels[$touch['type']->value],
                'is_paid'       => $touch['type']->isPaid(),
                'campaign_ref'  => $touch['campaign_ref'],
                'campaign_name' => $touch['campaign_ref'] ? ($campaignNames[$touch['campaign_ref']] ?? null) : null,
                'in_window'     => $date->greaterThanOrEqualTo($deadline->copy()->subDays($windowDays)),
                'days_to_next_purchase' => $nextPurchase ? $date->diffInDays($nextPurchase->date) : null,
            ];
        }

        foreach ($invoices as $invoice) {
            $events[] = [
                'id'         => 'invoice-'.$invoice->id,
                'type'       => 'invoice',
                'datetime'   => $invoice->date->toIso8601String(),
                'label'      => $invoice->reference,
                'net_amount' => (float) $invoice->net_amount,
            ];
        }

        usort($events, fn (array $a, array $b) => [$a['datetime'], $a['type']] <=> [$b['datetime'], $b['type']]);

        $totalEvents = count($events);

        return [
            'events'                  => array_slice($events, -self::MAX_EVENTS),
            'omitted_events'          => max(0, $totalEvents - self::MAX_EVENTS),
            'attribution'             => $this->attribution($customer, $labels),
            'attribution_window_days' => $windowDays,
            'currency_code'           => $customer->shop->currency->code,
        ];
    }

    /**
     * @return array<int, array{label: string, share: float, campaign: string|null}>
     */
    private function attribution(Customer $customer, array $labels): array
    {
        return $customer->trafficSources->map(function ($trafficSource) use ($labels) {
            $campaignId = $trafficSource->pivot->traffic_source_campaign_id;

            return [
                'label'    => $labels[$trafficSource->type] ?? $trafficSource->type,
                'share'    => (float) $trafficSource->pivot->share,
                'campaign' => $campaignId ? TrafficSourceCampaign::find($campaignId)?->name : null,
            ];
        })->all();
    }

    /**
     * @param array<int, string> $references
     *
     * @return array<string, string>
     */
    private function campaignNames(array $references): array
    {
        if (empty($references)) {
            return [];
        }

        return TrafficSourceCampaign::whereIn('reference', $references)
            ->pluck('name', 'reference')
            ->all();
    }

    private function attributionWindowDays(Customer $customer): int
    {
        return (int) (data_get($customer->shop->settings, 'marketing.attribution_window_days')
            ?? config('marketing.attribution_window_days', 90));
    }
}
