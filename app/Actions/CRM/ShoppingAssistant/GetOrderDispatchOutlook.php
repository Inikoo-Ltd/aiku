<?php

/*
 * Created: Thu, 19 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Dispatching\Capacity\GetWarehouseCapacity;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * INI-392. Where an order in our warehouse stands, for the AI shopping assistant: an estimated
 * dispatch day range worked out from this week's pace, or why it is stuck. Raul, 19 Nov 2026: an
 * estimate from data, always said to be one, never a promise; a stuck order is told plainly so the
 * customer can act. The queue size and the daily pace are commercially sensitive: they are used here
 * and never returned. Only the delivery note of this customer's own order is read; the queue is an
 * aggregate count.
 *
 * kind: estimate (from, to), waiting_decision (items a picker could not find, waiting for customer
 * services), warehouse_hold (held on our side), overdue (waiting far longer than this week's pace).
 */
class GetOrderDispatchOutlook
{
    use AsObject;

    public const int CACHE_SECONDS = 900;

    public const float FAST = 1.2;

    public const float SLOW = 0.8;

    public const int OVERDUE_MIN_HOURS = 120;

    public const int OVERDUE_MEDIANS = 3;

    public const int MAX_DAYS = 15;

    /**
     * @return array{kind: string, from?: Carbon, to?: Carbon, items?: array<int, string>, delivery_note_id: int}|null
     */
    public function handle(Order $order, ?Carbon $now = null): ?array
    {
        $now  = $now ?? now();
        $note = $order->deliveryNotes()
            ->whereNotIn('state', [DeliveryNoteStateEnum::DISPATCHED->value, DeliveryNoteStateEnum::CANCELLED->value])
            ->latest('id')
            ->first();

        if (!$note || !$note->warehouse_id) {
            return null;
        }

        if ($note->number_items_waiting_crm > 0) {
            return ['kind' => 'waiting_decision', 'items' => $this->waitingItems($note), 'delivery_note_id' => $note->id];
        }

        if ($note->state === DeliveryNoteStateEnum::HANDLING_BLOCKED || $note->number_items_waiting_warehouse > 0 || $order->is_handling_on_hold) {
            return ['kind' => 'warehouse_hold', 'delivery_note_id' => $note->id];
        }

        if (!in_array($note->state, GetWarehouseCapacity::WAITING_TO_BE_PICKED, true)) {
            return null;
        }

        $stream   = $this->streamOf($note->shop_id);
        $capacity = Cache::remember('dispatch-outlook-capacity:'.$note->warehouse_id.':'.$stream, self::CACHE_SECONDS, fn () => GetWarehouseCapacity::run($note->warehouse, $stream));
        $pace     = (float) ($capacity['out_per_day'] ?? 0);
        $placed   = Carbon::parse($note->date);
        $overdue  = max(self::OVERDUE_MIN_HOURS, self::OVERDUE_MEDIANS * (float) ($capacity['median_hours'] ?? 0));

        if ($placed->diffInHours($now) > $overdue) {
            return ['kind' => 'overdue', 'delivery_note_id' => $note->id];
        }

        if ($pace <= 0) {
            return null;
        }

        $ahead = $this->ordersAhead($note, $stream);
        $fast  = (int) floor($ahead / ($pace * self::FAST));
        $slow  = (int) ceil(($ahead + 1) / ($pace * self::SLOW));

        if ($slow > self::MAX_DAYS) {
            return null;
        }

        $timezone = $note->warehouse->organisation->timezone?->name ?? 'UTC';

        return [
            'kind'             => 'estimate',
            'from'             => $this->workingDaysFrom($now, $fast, $timezone),
            'to'               => $this->workingDaysFrom($now, max($slow, $fast), $timezone),
            'delivery_note_id' => $note->id,
        ];
    }

    private function streamOf(?int $shopId): string
    {
        $isDropshipping = $shopId && DB::table('shops')->where('id', $shopId)->whereRaw(Shop::dispatchingChannelSql().' = ?', [ShopTypeEnum::DROPSHIPPING->value])->exists();

        return $isDropshipping ? GetWarehouseCapacity::DROPSHIPPING : GetWarehouseCapacity::WHOLESALE;
    }

    /**
     * Delivery notes of the same warehouse and stream that will leave before this one: every one
     * already picked or being packed, and those waiting to be picked that are older or have premium
     * dispatch. Notes on hold are left out: they do not hold this one up.
     */
    private function ordersAhead(DeliveryNote $note, string $stream): int
    {
        $dropshippingShopIds = DB::table('shops')->whereRaw(Shop::dispatchingChannelSql().' = ?', [ShopTypeEnum::DROPSHIPPING->value])->pluck('id');
        $toPick              = array_map(fn (DeliveryNoteStateEnum $state) => $state->value, array_filter(GetWarehouseCapacity::WAITING_TO_BE_PICKED, fn (DeliveryNoteStateEnum $state) => $state !== DeliveryNoteStateEnum::HANDLING_BLOCKED));
        $afterPick           = array_map(fn (DeliveryNoteStateEnum $state) => $state->value, [...GetWarehouseCapacity::WAITING_TO_BE_PACKED, ...GetWarehouseCapacity::WAITING_TO_BE_SHIPPED]);

        return DB::table('delivery_notes')
            ->where('warehouse_id', $note->warehouse_id)
            ->whereNull('deleted_at')
            ->where('id', '!=', $note->id)
            ->when(
                $stream === GetWarehouseCapacity::DROPSHIPPING,
                fn ($query) => $query->whereIn('shop_id', $dropshippingShopIds),
                fn ($query) => $query->where(fn ($query) => $query->whereNotIn('shop_id', $dropshippingShopIds)->orWhereNull('shop_id'))
            )
            ->where(fn ($query) => $query->whereIn('state', $afterPick)
                ->orWhere(fn ($query) => $query->whereIn('state', $toPick)->where(fn ($query) => $query->where('date', '<', $note->date)->orWhere('is_premium_dispatch', true))))
            ->count();
    }

    /**
     * @return array<int, string>
     */
    private function waitingItems(DeliveryNote $note): array
    {
        return DB::table('delivery_note_items')
            ->join('transactions', 'transactions.id', '=', 'delivery_note_items.transaction_id')
            ->join('products', fn ($join) => $join->on('products.id', '=', 'transactions.model_id')->where('transactions.model_type', 'Product'))
            ->where('delivery_note_items.delivery_note_id', $note->id)
            ->where('delivery_note_items.quantity_waiting_crm', '>', 0)
            ->orderBy('products.code')
            ->limit(10)
            ->pluck('products.name')
            ->unique()
            ->values()
            ->all();
    }

    private function workingDaysFrom(Carbon $now, int $days, string $timezone): Carbon
    {
        $day = $now->copy()->setTimezone($timezone)->startOfDay();
        while ($day->isWeekend()) {
            $day->addDay();
        }
        for ($added = 0; $added < $days;) {
            $day->addDay();
            if (!$day->isWeekend()) {
                $added++;
            }
        }

        return $day;
    }
}
