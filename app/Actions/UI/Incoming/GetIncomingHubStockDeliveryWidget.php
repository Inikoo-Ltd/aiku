<?php

namespace App\Actions\UI\Incoming;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIncomingHubStockDeliveryWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse, array $parentTypes): array
    {
        $organisation = $warehouse->organisation;
        $since        = now()->subDays(6)->startOfDay();
        $lastWeek     = $since->format('Ymd').'-'.now()->format('Ymd');

        $countsByState = DB::table('stock_deliveries')
            ->where('organisation_id', $organisation->id)
            ->whereIn('parent_type', $parentTypes)
            ->whereNull('deleted_at')
            ->whereIn('state', [
                StockDeliveryStateEnum::DISPATCHED->value,
                StockDeliveryStateEnum::RECEIVED->value,
                StockDeliveryStateEnum::CHECKED->value,
                StockDeliveryStateEnum::BOOKING_IN->value,
                StockDeliveryStateEnum::BOOKED_IN->value,
                StockDeliveryStateEnum::PLACED->value,
            ])
            ->where(function ($query) use ($since) {
                $query->whereNotIn('state', [StockDeliveryStateEnum::BOOKED_IN->value, StockDeliveryStateEnum::PLACED->value])
                    ->orWhere(fn ($query) => $query->where('state', StockDeliveryStateEnum::BOOKED_IN->value)->where('booked_in_at', '>=', $since))
                    ->orWhere(fn ($query) => $query->where('state', StockDeliveryStateEnum::PLACED->value)->where('placed_at', '>=', $since));
            })
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        $routeParams = [
            'organisation'     => $organisation->slug,
            'warehouse'        => $warehouse->slug,
            'elements[source]' => implode(',', $parentTypes),
        ];

        $stateConfig = [
            'arriving'                                => ['icon' => ['fal', 'fa-truck'],            'label' => __('Arriving'), 'tooltip' => __('Dispatched, expected to arrive soon'), 'state' => StockDeliveryStateEnum::DISPATCHED->value],
            StockDeliveryStateEnum::RECEIVED->value   => ['icon' => ['fal', 'fa-chair'],            'label' => __('Received')],
            StockDeliveryStateEnum::CHECKED->value    => ['icon' => ['fal', 'fa-clipboard-check'],  'label' => __('Checked')],
            StockDeliveryStateEnum::BOOKING_IN->value => ['icon' => ['fal', 'fa-clipboard-list'],   'label' => __('Booking In')],
            StockDeliveryStateEnum::BOOKED_IN->value  => ['icon' => ['fal', 'fa-pallet-alt'],       'label' => __('Booked In'), 'tooltip' => __('Booked in, last 7 days')],
        ];

        $placed = $countsByState[StockDeliveryStateEnum::PLACED->value] ?? 0;

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];
        $total      = 0;

        foreach ($stateConfig as $stateValue => $config) {
            $count = $countsByState[$config['state'] ?? $stateValue] ?? 0;

            $metrics[] = [
                'key'     => $stateValue,
                'label'   => $config['label'],
                'type'    => 'stat',
                'icon'    => $config['icon'],
                'tooltip' => $config['tooltip'] ?? $config['label'],
            ];

            $entry = [
                'value'        => $count,
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                    'parameters' => [...$routeParams, 'elements[state]' => $config['state'] ?? $stateValue],
                ],
            ];

            if ($stateValue === StockDeliveryStateEnum::BOOKED_IN->value) {
                $entry['route_target']['parameters']['between[booked_in_at]'] = $lastWeek;
                $entry['suffix'] = [
                    'value'   => $placed,
                    'tooltip' => __('Placed, last 7 days'),
                    'route_target' => [
                        'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                        'parameters' => [...$routeParams, 'elements[state]' => StockDeliveryStateEnum::PLACED->value, 'between[placed_at]' => $lastWeek],
                    ],
                ];
            }

            $dataGlobal[$stateValue] = $entry;

            $totals[$stateValue] = ['value' => $count];
            $total              += $count;
        }

        return [
            'metrics'    => $metrics,
            'data'       => ['_global' => $dataGlobal],
            'row_totals' => [
                '_global' => [
                    'value'        => $total,
                    'route_target' => [
                        'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                        'parameters' => $routeParams,
                    ],
                ],
            ],
            'totals'      => $totals,
            'grand_total' => [
                'value'   => $total,
                'icon'    => ['fal', 'fa-truck-container'],
                'tooltip' => __('Total Stock Deliveries'),
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                    'parameters' => $routeParams,
                ],
            ],
        ];
    }
}
