<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The modal procurement gets on its first page of the day while deliveries the warehouse is booking in, or has
 * booked in, still miss their costs. It stays until acknowledged, and a delivery waiting more than
 * REASON_AFTER_DAYS working days (Monday to Friday) needs a reason; a reason may leave it out until a date chosen then. Worked out at most once a day for users with nothing to see.
 */
class GetStockDeliveriesCostingReminder
{
    use AsObject;

    public const int REASON_AFTER_DAYS = 5;

    public static function cacheKey(User $user): string
    {
        return "costing-reminder:$user->id:".now()->toDateString();
    }

    public function handle(User $user): ?array
    {
        if (Cache::has(self::cacheKey($user))) {
            return null;
        }

        $deliveries = $this->deliveries($user);

        if (!$deliveries) {
            Cache::put(self::cacheKey($user), true, now()->endOfDay());

            return null;
        }

        return [
            'reason_after_days' => self::REASON_AFTER_DAYS,
            'remind_on_max'     => now()->addMonth()->toDateString(),
            'deliveries'        => $deliveries,
            'acknowledge_route' => ['name' => 'grp.models.costing_reminder.acknowledge', 'parameters' => [], 'method' => 'post'],
        ];
    }

    /**
     * @return array<int, array{id: int, reference: string, organisation: string, days: int, needs_reason: bool, route: array}>
     */
    public function deliveries(User $user): array
    {
        $organisations = $user->authorisedOrganisations()->get(['organisations.id', 'organisations.slug', 'organisations.code'])
            ->filter(fn (Organisation $organisation) => $user->hasAnyRole(["procurement-clerk-$organisation->id", "procurement-supervisor-$organisation->id"]))
            ->keyBy('id');

        if ($organisations->isEmpty()) {
            return [];
        }

        return StockDelivery::whereIn('organisation_id', $organisations->keys())
            ->whereIn('state', [StockDeliveryStateEnum::BOOKING_IN, StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED])
            ->withCostsMissing()
            ->where(fn ($query) => $query->whereNull('data->costing_reminder_snoozed_until')->orWhere('data->costing_reminder_snoozed_until', '<=', now()->toDateString()))
            ->get(['id', 'reference', 'slug', 'organisation_id', 'booking_in_at', 'booked_in_at', 'placed_at'])
            ->map(function (StockDelivery $stockDelivery) use ($organisations) {
                $organisation = $organisations->get($stockDelivery->organisation_id);
                $days         = (int) Carbon::parse($stockDelivery->booking_in_at ?? $stockDelivery->booked_in_at ?? $stockDelivery->placed_at)->startOfDay()->diffInWeekdays(now()->startOfDay());

                return [
                    'id'           => $stockDelivery->id,
                    'reference'    => $stockDelivery->reference,
                    'organisation' => $organisation->code,
                    'days'         => $days,
                    'needs_reason' => $days > self::REASON_AFTER_DAYS,
                    'route'        => ['name' => 'grp.org.procurement.stock_deliveries.show', 'parameters' => [$organisation->slug, $stockDelivery->slug]],
                ];
            })
            ->sortByDesc('days')
            ->values()
            ->all();
    }
}
