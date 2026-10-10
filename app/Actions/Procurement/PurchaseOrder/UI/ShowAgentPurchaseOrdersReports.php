<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Twelve months of an agent's purchase orders: what was submitted each month, how long each supplier takes to
 * confirm, whether their deliveries arrive by the date the order expected them, and how the value splits
 * between the client organisations. Drafts never submitted are not part of any of it.
 */
class ShowAgentPurchaseOrdersReports extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private const int MONTHS = 12;

    public function handle(Agent $agent): array
    {
        $organisation         = $agent->organisation;
        $groupToAgentExchange = GetCurrencyExchange::run($organisation->group->currency, $organisation->currency);
        $currency             = $groupToAgentExchange ? $organisation->currency->code : $organisation->group->currency->code;
        $toAgentCurrency      = 'purchase_orders.grp_exchange * '.(float) ($groupToAgentExchange ?: 1);
        $from                 = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $submitted = fn (): Builder => DB::table('purchase_orders')
            ->where('purchase_orders.agent_id', $agent->id)
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.state', '!=', 'cancelled')
            ->whereNotNull('purchase_orders.submitted_at');

        $byMonth = $submitted()
            ->where('submitted_at', '>=', $from)
            ->selectRaw("to_char(date_trunc('month', submitted_at), 'YYYY-MM') as month, count(*) as number, sum(cost_total * {$toAgentCurrency}) as value")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(0, self::MONTHS - 1))->map(function (int $offset) use ($from, $byMonth) {
            $month = $from->copy()->addMonths($offset)->format('Y-m');

            return [
                'month'  => $month,
                'number' => (int) ($byMonth[$month]->number ?? 0),
                'value'  => (float) ($byMonth[$month]->value ?? 0),
            ];
        })->values();

        $confirmation = $submitted()
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '>=', $from)
            ->selectRaw(
                'parent_id, parent_name, count(*) as number,
                percentile_cont(0.5) within group (order by extract(epoch from confirmed_at - submitted_at) / 86400) as median_days,
                avg(extract(epoch from confirmed_at - submitted_at) / 86400) as average_days,
                max(extract(epoch from confirmed_at - submitted_at) / 86400) as slowest_days'
            )
            ->groupBy('parent_id', 'parent_name')
            ->orderByDesc('number')
            ->get()
            ->map(fn ($row) => [
                'id'           => $row->parent_id,
                'name'         => $row->parent_name,
                'number'       => (int) $row->number,
                'median_days'  => round((float) $row->median_days, 1),
                'average_days' => round((float) $row->average_days, 1),
                'slowest_days' => round((float) $row->slowest_days, 1),
            ])
            ->values();

        $onTime = $submitted()
            ->whereNotNull('purchase_orders.estimated_received_at')
            ->joinSub(
                DB::table('purchase_order_stock_delivery')
                    ->join('stock_deliveries', 'stock_deliveries.id', '=', 'purchase_order_stock_delivery.stock_delivery_id')
                    ->whereNull('stock_deliveries.deleted_at')
                    ->whereNotNull('stock_deliveries.received_at')
                    ->selectRaw('purchase_order_id, min(received_at) as received_at')
                    ->groupBy('purchase_order_id'),
                'deliveries',
                'deliveries.purchase_order_id',
                '=',
                'purchase_orders.id'
            )
            ->where('deliveries.received_at', '>=', $from)
            ->selectRaw(
                'parent_id, parent_name, count(*) as number,
                count(*) filter (where deliveries.received_at::date <= estimated_received_at::date) as on_time,
                avg(extract(epoch from deliveries.received_at - estimated_received_at) / 86400) filter (where deliveries.received_at::date > estimated_received_at::date) as average_days_late'
            )
            ->groupBy('parent_id', 'parent_name')
            ->orderByDesc('number')
            ->get()
            ->map(fn ($row) => [
                'id'                => $row->parent_id,
                'name'              => $row->parent_name,
                'number'            => (int) $row->number,
                'on_time'           => (int) $row->on_time,
                'on_time_percent'   => round(100 * $row->on_time / max(1, $row->number)),
                'average_days_late' => $row->average_days_late === null ? null : round((float) $row->average_days_late, 1),
            ])
            ->values();

        $organisations = $submitted()
            ->where('submitted_at', '>=', $from)
            ->join('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
            ->selectRaw("organisations.name, organisations.code, count(*) as number, sum(cost_total * {$toAgentCurrency}) as value")
            ->groupBy('organisations.id', 'organisations.name', 'organisations.code')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'name'   => $row->name,
                'code'   => $row->code,
                'number' => (int) $row->number,
                'value'  => (float) $row->value,
            ])
            ->values();

        return [
            'currency'      => $currency,
            'months'        => $months,
            'confirmation'  => $confirmation,
            'on_time'       => $onTime,
            'organisations' => $organisations,
            'from'          => $from->toDateString(),
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentPurchaseOrdersReports',
            [
                'title'       => __('Purchase orders reports'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Purchase orders'),
                                'route' => [
                                    'name'       => 'grp.org.agent.purchase_orders.dashboard',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Reports'),
                                'route' => [
                                    'name'       => 'grp.org.agent.purchase_orders.reports',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Purchase orders reports'),
                        'icon'  => 'fal fa-chart-line',
                    ],
                    'title' => __('Purchase orders reports'),
                ],
                'data'        => $data,
            ]
        );
    }
}
