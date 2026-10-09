<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * An agent fills about one container per organisation at a time, so its containers fit on one board, from being
 * filled to arrived, each card carrying what the container holds.
 */
class ShowAgentContainersBoard extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private const int ARRIVED_DAYS = 30;

    private const int CARDS_PER_COLUMN = 30;

    public function handle(Agent $agent): array
    {
        $arrivedStates = [
            StockDeliveryStateEnum::RECEIVED,
            StockDeliveryStateEnum::CHECKED,
            StockDeliveryStateEnum::BOOKING_IN,
            StockDeliveryStateEnum::BOOKED_IN,
            StockDeliveryStateEnum::PLACED,
        ];

        $columns = [
            'in_process'    => [__('Being filled'), [StockDeliveryStateEnum::IN_PROCESS], 'date'],
            'confirmed'     => [__('Confirmed'), [StockDeliveryStateEnum::CONFIRMED], 'confirmed_at'],
            'ready_to_ship' => [__('Ready to ship'), [StockDeliveryStateEnum::READY_TO_SHIP], 'ready_to_ship_at'],
            'dispatched'    => [__('On the way'), [StockDeliveryStateEnum::DISPATCHED], 'dispatched_at'],
            'arrived'       => [__('Arrived, last :days days', ['days' => self::ARRIVED_DAYS]), $arrivedStates, 'received_at'],
        ];

        $stockDeliveries = StockDelivery::where('agent_id', $agent->id)
            ->whereIn('state', array_map(fn (StockDeliveryStateEnum $state) => $state->value, array_merge(...array_column($columns, 1))))
            ->where(fn ($query) => $query
                ->whereNotIn('state', array_map(fn (StockDeliveryStateEnum $state) => $state->value, $arrivedStates))
                ->orWhere('received_at', '>=', now()->subDays(self::ARRIVED_DAYS)))
            ->with(['organisation:id,name,code', 'currency:id,code'])
            ->orderBy('date')
            ->get();

        return collect($columns)->map(function (array $column, string $key) use ($stockDeliveries) {
            [$label, $states, $sinceColumn] = $column;

            $inColumn = $stockDeliveries->filter(fn (StockDelivery $stockDelivery) => in_array($stockDelivery->state, $states, true));

            $cards = $inColumn
                ->take(self::CARDS_PER_COLUMN)
                ->map(fn (StockDelivery $stockDelivery) => [
                    'id'              => $stockDelivery->id,
                    'reference'       => $stockDelivery->reference,
                    'organisation'    => $stockDelivery->organisation?->name,
                    'state_label'     => $stockDelivery->state->labels()[$stockDelivery->state->value] ?? $stockDelivery->state->value,
                    'purchase_orders' => (int) $stockDelivery->number_purchase_orders,
                    'items'           => (int) $stockDelivery->number_stock_delivery_items_except_cancelled,
                    'cbm'             => $stockDelivery->cbm !== null ? (float) $stockDelivery->cbm : null,
                    'gross_weight'    => $stockDelivery->gross_weight !== null ? (float) $stockDelivery->gross_weight : null,
                    'amount'          => (float) $stockDelivery->cost_total,
                    'currency'        => $stockDelivery->currency?->code,
                    'days_in_state'   => (int) Carbon::parse($stockDelivery->{$sinceColumn} ?? $stockDelivery->date)->diffInDays(now()),
                    'route'           => [
                        'name'       => 'grp.org.procurement.stock_deliveries.show',
                        'parameters' => [$this->organisation->slug, $stockDelivery->slug],
                    ],
                ])
                ->values();

            return [
                'key'   => $key,
                'label' => $label,
                'total' => $inColumn->count(),
                'cards' => $cards,
            ];
        })->values()->all();
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent);
    }

    public function htmlResponse(array $columns, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentContainersBoard',
            [
                'title'       => __('Containers board'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Containers'),
                                'route' => [
                                    'name'       => 'grp.org.agent.stock_deliveries.board',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Containers'),
                        'icon'  => 'fal fa-truck-container',
                    ],
                    'title' => __('Containers board'),
                ],
                'columns'     => $columns,
            ]
        );
    }
}
