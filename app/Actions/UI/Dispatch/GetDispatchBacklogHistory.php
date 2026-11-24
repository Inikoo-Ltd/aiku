<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\Dispatching\DeliveryNote\UI\WithDeliveryNotesChannel;
use App\Actions\Helpers\Ticket\UI\ShowTicketsReports;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchBacklogHistory
{
    use AsObject;
    use WithDeliveryNotesChannel;

    public const string DEFAULT_INTERVAL = '1m';

    public static function intervalOptions(): array
    {
        $labels = DateIntervalEnum::labels();

        return [
            '1w' => $labels['1w'],
            '1m' => $labels['1m'],
            'lm' => $labels['lm'],
            '1q' => $labels['1q'],
            '1y' => $labels['1y'],
            '18m' => __('1.5 Years'),
        ];
    }

    /**
     * @return array{interval: string, channels: array<int, array{value: string, label: string}>, daily: array<int, array{date: string, channels: array<string, array{backlog: int, created: int, dispatched: int, cancelled: int}>}>}
     */
    public function handle(Warehouse $warehouse, string $interval): array
    {
        if (!array_key_exists($interval, self::intervalOptions())) {
            $interval = self::DEFAULT_INTERVAL;
        }

        $timezone    = $warehouse->organisation->timezone?->name ?? 'UTC';
        $zone        = DB::getPdo()->quote($timezone);
        [$from, $to] = $interval === '18m'
            ? [now($timezone)->subMonths(18)->startOfDay()->utc(), now($timezone)->endOfDay()->utc()]
            : ShowTicketsReports::make()->range($interval, null, $timezone);

        $closedAt = "case delivery_notes.state when 'dispatched' then coalesce(delivery_notes.dispatched_at, delivery_notes.finalised_at, delivery_notes.packed_at, delivery_notes.picked_at, delivery_notes.handling_at, delivery_notes.queued_at, delivery_notes.created_at) when 'cancelled' then coalesce(delivery_notes.cancelled_at, delivery_notes.handling_at, delivery_notes.queued_at, delivery_notes.created_at) end";
        $channel  = "case when ".Shop::dispatchingChannelSql()." = '".ShopTypeEnum::B2B->value."' and exists (select 1 from org_partners where org_partners.partner_id = delivery_notes.organisation_id and org_partners.customer_id = delivery_notes.customer_id) then '".self::PARTNERS_CHANNEL."' else ".Shop::dispatchingChannelSql()." end";

        $deliveryNotes = DB::table('delivery_notes')
            ->join('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->where('delivery_notes.created_at', '<=', $to)
            ->selectRaw("delivery_notes.created_at, delivery_notes.state, $closedAt as closed_at, $channel as channel");

        $rows = DB::query()->fromSub($deliveryNotes, 'dn')
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $from))
            ->selectRaw("
                channel,
                count(*) filter (where created_at < ?) as opening_backlog,
                to_char(created_at at time zone $zone, 'YYYY-MM-DD') as created_day,
                to_char(closed_at at time zone $zone, 'YYYY-MM-DD') as closed_day,
                state,
                count(*) as total", [$from])
            ->groupBy('channel', 'created_day', 'closed_day', 'state')
            ->get();

        $opening = [];
        $deltas  = [];
        foreach ($rows as $row) {
            $opening[$row->channel] = ($opening[$row->channel] ?? 0) + (int) $row->opening_backlog;
            $createdInRange         = (int) $row->total - (int) $row->opening_backlog;
            if ($createdInRange) {
                $deltas[$row->created_day][$row->channel]['created'] = ($deltas[$row->created_day][$row->channel]['created'] ?? 0) + $createdInRange;
            }
            if ($row->closed_day && in_array($row->state, ['dispatched', 'cancelled'])) {
                $deltas[$row->closed_day][$row->channel][$row->state] = ($deltas[$row->closed_day][$row->channel][$row->state] ?? 0) + (int) $row->total;
            }
        }

        $channels = collect(array_keys($opening + array_merge([], ...array_values($deltas))))
            ->sortBy(fn (string $channel) => array_search($channel, ['b2b', self::PARTNERS_CHANNEL, 'external', 'b2c', 'dropshipping', 'fulfilment']))
            ->values();

        $backlog = $opening;
        $daily   = [];
        $cursor  = $from->copy()->setTimezone($timezone)->startOfDay();
        while ($cursor->lte($to)) {
            $day      = $cursor->toDateString();
            $byChannel = [];
            foreach ($channels as $channelKey) {
                $change                = $deltas[$day][$channelKey] ?? [];
                $backlog[$channelKey]  = ($backlog[$channelKey] ?? 0) + ($change['created'] ?? 0) - ($change['dispatched'] ?? 0) - ($change['cancelled'] ?? 0);
                $byChannel[$channelKey] = [
                    'backlog'    => $backlog[$channelKey],
                    'created'    => $change['created'] ?? 0,
                    'dispatched' => $change['dispatched'] ?? 0,
                    'cancelled'  => $change['cancelled'] ?? 0,
                ];
            }
            $daily[] = ['date' => $day, 'channels' => $byChannel];
            $cursor->addDay();
        }

        return [
            'interval' => $interval,
            'options'  => self::intervalOptions(),
            'channels' => $channels->map(fn (string $channelKey) => ['value' => $channelKey, 'label' => $this->channelLabel($channelKey)])->all(),
            'daily'    => $daily,
        ];
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            ShopTypeEnum::B2B->value          => __('Wholesale'),
            self::PARTNERS_CHANNEL            => __('Partners'),
            ShopTypeEnum::EXTERNAL->value     => __('Marketplaces'),
            ShopTypeEnum::B2C->value          => __('B2C'),
            ShopTypeEnum::DROPSHIPPING->value => __('Dropshipping'),
            default                           => ucfirst($channel),
        };
    }
}
