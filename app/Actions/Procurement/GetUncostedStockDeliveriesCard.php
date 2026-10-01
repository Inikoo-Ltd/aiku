<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUncostedStockDeliveriesCard
{
    use AsObject;

    public const BOOKED_IN_GRACE_DAYS = 3;

    public const AGENT_LOOKBACK_DAYS = 90;

    public function handle(Organisation $organisation): ?array
    {
        $bookedInCutoff = now()->subDays(self::BOOKED_IN_GRACE_DAYS)->startOfDay();
        $agentSince     = now()->subDays(self::AGENT_LOOKBACK_DAYS)->startOfDay();

        $stale = StockDelivery::where('organisation_id', $organisation->id)
            ->where('state', StockDeliveryStateEnum::BOOKED_IN)
            ->where('booked_in_at', '<', $bookedInCutoff)
            ->selectRaw('count(*) as total, min(booked_in_at) as oldest')
            ->first();

        $agentNotCosted = StockDelivery::where('organisation_id', $organisation->id)
            ->where('parent_type', 'OrgAgent')
            ->where('state', StockDeliveryStateEnum::PLACED)
            ->where('is_costed', false)
            ->where('placed_at', '>=', $agentSince)
            ->count();

        if (!$stale->total && !$agentNotCosted) {
            return null;
        }

        $staleRoute = $this->route($organisation, [
            'elements[state]'       => StockDeliveryStateEnum::BOOKED_IN->value,
            'between[booked_in_at]' => ($stale->oldest ? Carbon::parse($stale->oldest) : $bookedInCutoff)->format('Ymd').'-'.$bookedInCutoff->copy()->subDay()->format('Ymd'),
        ]);

        $agentRoute = $this->route($organisation, [
            'elements[state]'   => StockDeliveryStateEnum::PLACED->value,
            'elements[source]'  => 'OrgAgent',
            'elements[costing]' => 'not_costed',
            'between[placed_at]' => $agentSince->format('Ymd').'-'.now()->format('Ymd'),
        ]);

        return [
            'label'       => __('Not costed'),
            'description' => __('Stock deliveries waiting for costing'),
            'icon'        => 'fal fa-exclamation-triangle',
            'value'       => $stale->total + $agentNotCosted,
            'tone'        => 'amber',
            'route'       => $stale->total ? $staleRoute : $agentRoute,
            'metrics'     => [
                [
                    'label' => __('Booked in more than :days days ago', ['days' => self::BOOKED_IN_GRACE_DAYS]),
                    'value' => $stale->total,
                    'route' => $staleRoute,
                ],
                [
                    'label' => __('From agents, placed in the last :days days', ['days' => self::AGENT_LOOKBACK_DAYS]),
                    'value' => $agentNotCosted,
                    'route' => $agentRoute,
                ],
            ],
        ];
    }

    private function route(Organisation $organisation, array $query): array
    {
        return [
            'name'       => 'grp.org.procurement.stock_deliveries.index',
            'parameters' => [
                'organisation' => $organisation->slug,
                '_query'       => $query,
            ],
        ];
    }
}
