<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Ordering\Order;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrderMarketingJourney
{
    use AsObject;

    /**
     * The marketing story of one order on a single time axis: the touches that preceded it, each
     * product landing in the basket, and the order milestones, ending at submission. For the
     * customer's first order the axis starts at the beginning - the touches that led to
     * registration are the acquisition story and belong on it; for a repeat order it starts after
     * the previous order, since older touches already told their story there.
     *
     * @return array{is_first_order: bool, events: array<int, array<string, mixed>>, attribution: array<int, array{label: string, share: float, campaign: string|null}>, currency_code: string}
     */
    public function handle(Order $order): array
    {
        $customer = $order->customer;
        $labels   = TrafficSourcesTypeEnum::labels();
        $end      = $order->submitted_at ?? now();

        $previousOrderDate = $customer
            ? $customer->orders()
                ->where('orders.id', '!=', $order->id)
                ->where('orders.date', '<', $order->date)
                ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->max('orders.date')
            : null;

        $isFirstOrder = $previousOrderDate === null;
        $rangeStart   = $previousOrderDate ? Carbon::parse($previousOrderDate) : null;

        $events = [];

        $touches = collect(ParseTrafficSourceTouches::run($customer?->traffic_sources))
            ->filter(fn (array $touch) => $touch['timestamp'] !== null)
            ->map(fn (array $touch) => [...$touch, 'date' => Carbon::createFromTimestamp($touch['timestamp'])])
            ->filter(fn (array $touch) => $touch['date']->lessThanOrEqualTo($end)
                && (!$rangeStart || $touch['date']->greaterThan($rangeStart)))
            ->sortBy('timestamp')
            ->values();

        $campaignNames = TrafficSourceCampaign::whereIn(
            'reference',
            $touches->pluck('campaign_ref')->filter()->unique()->all()
        )->pluck('name', 'reference');

        /* Which channels this order actually credited: a touch on the axis reads differently when
           the split below names it. */
        $attributedTypes = $order->trafficSources->pluck('type')->all();

        foreach ($touches as $index => $touch) {
            $events[] = [
                'id'            => 'touch-'.$index,
                'type'          => 'touch',
                'datetime'      => $touch['date']->toIso8601String(),
                'label'         => $labels[$touch['type']->value],
                'is_paid'       => $touch['type']->isPaid(),
                'campaign_name' => $touch['campaign_ref']
                    ? ($campaignNames[$touch['campaign_ref']] ?? $touch['campaign_ref'])
                    : null,
                'attributed'    => in_array($touch['type']->value, $attributedTypes, true),
            ];
        }

        if ($isFirstOrder && $customer) {
            $events[] = [
                'id'       => 'registration',
                'type'     => 'registration',
                'datetime' => $customer->created_at->toIso8601String(),
                'label'    => __('Registered'),
            ];
        }

        $events[] = [
            'id'       => 'order-created',
            'type'     => 'order',
            'datetime' => $order->created_at->toIso8601String(),
            'label'    => __('Basket opened'),
        ];

        foreach ($order->transactions()->where('model_type', 'Product')->orderBy('created_at')->get() as $transaction) {
            $events[] = [
                'id'       => 'product-'.$transaction->id,
                'type'     => 'product',
                'datetime' => $transaction->created_at->toIso8601String(),
                'label'    => $transaction->asset?->name ?? $transaction->asset?->code ?? __('Product'),
                'quantity' => (float) $transaction->quantity_ordered,
            ];
        }

        if ($order->submitted_at) {
            $events[] = [
                'id'       => 'order-submitted',
                'type'     => 'order',
                'datetime' => $order->submitted_at->toIso8601String(),
                'label'    => __('Order submitted'),
            ];
        }

        usort($events, fn (array $a, array $b) => $a['datetime'] <=> $b['datetime']);

        return [
            'is_first_order' => $isFirstOrder,
            'events'         => $events,
            'attribution'    => $order->trafficSources->map(fn ($trafficSource) => [
                'label'    => $labels[$trafficSource->type] ?? $trafficSource->type,
                'share'    => (float) $trafficSource->pivot->share,
                'campaign' => $trafficSource->pivot->traffic_source_campaign_id
                    ? TrafficSourceCampaign::find($trafficSource->pivot->traffic_source_campaign_id)?->name
                    : null,
            ])->all(),
            'currency_code'  => $order->currency->code,
        ];
    }
}
