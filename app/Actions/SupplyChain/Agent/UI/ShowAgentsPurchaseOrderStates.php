<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Pending purchase orders placed through each agent (not cancelled, closed or received yet), by where they stand and how long ago they were sent: how many orders, product lines and how much,
 * in the group currency.
 */
class ShowAgentsPurchaseOrderStates extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use WithAgentsSubNavigation;

    public function handle(Group $group): array
    {
        $progress = self::stageSql();
        $labels   = self::stageLabels();

        $age = self::ageSql();

        $rows = DB::table('purchase_orders')
            ->join('agents', 'agents.id', '=', 'purchase_orders.agent_id')
            ->join('organisations as clients', 'clients.id', '=', 'purchase_orders.organisation_id')
            ->where('agents.group_id', $group->id)
            ->whereNull('purchase_orders.deleted_at')
            ->where(fn ($query) => self::wherePending($query))
            ->selectRaw(
                "agents.slug, clients.slug as client, ($progress) as stage, ($age) as age,
                (purchase_orders.deposit_paid_at is not null) as has_deposit,
                count(*) as number_purchase_orders,
                coalesce(sum(purchase_orders.number_current_purchase_order_transactions), 0) as number_lines,
                coalesce(sum(purchase_orders.cost_items * coalesce(purchase_orders.grp_exchange, 1)), 0) as amount"
            )
            ->groupBy('agents.slug', 'clients.slug', 'stage', 'age', 'has_deposit')
            ->get();

        return [
            'stages'  => collect(array_keys($labels))
                ->map(fn (string $stage) => ['key' => $stage, 'label' => $labels[$stage], 'information' => self::stageInformation()[$stage] ?? null])
                ->values()
                ->all(),
            'ages'    => collect(self::ageBuckets())
                ->map(fn (array $bucket, string $key) => ['key' => $key, 'label' => $bucket[0]])
                ->values()
                ->all(),
            'agents'  => DB::table('agents')
                ->join('organisations', 'organisations.id', '=', 'agents.organisation_id')
                ->whereIn('agents.slug', $rows->pluck('slug')->unique())
                ->orderBy('organisations.code')
                ->get(['agents.slug', 'organisations.code', 'agents.name'])
                ->all(),
            'clients' => DB::table('organisations')
                ->whereIn('slug', $rows->pluck('client')->unique())
                ->orderBy('code')
                ->get(['slug', 'code', 'name'])
                ->map(fn ($client) => ['key' => $client->slug, 'label' => strtoupper($client->code), 'name' => $client->name])
                ->all(),
            'figures' => $rows->map(fn ($row) => [
                'agent'                  => $row->slug,
                'client'                 => $row->client,
                'stage'                  => $row->stage,
                'age'                    => $row->age,
                'deposit'                => $row->has_deposit ? 'deposit' : 'no_deposit',
                'number_purchase_orders' => (int) $row->number_purchase_orders,
                'number_lines'           => (int) $row->number_lines,
                'amount'                 => (float) $row->amount,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, string> the columns of the page, in the order the goods move
     */
    public static function stageLabels(): array
    {
        return [
            'draft'              => __('Draft'),
            'submitted'          => __('Submitted'),
            'confirmed'          => __('Confirmed'),
            'in_production'      => __('Being made'),
            'in_agent_warehouse'  => __('Received'),
            'ready_for_container' => __('Packed'),
            'on_the_way'          => __('Container on the way'),
        ];
    }

    /**
     * @return array<string, string> what a column means, for the columns whose name does not say it all
     */
    public static function stageInformation(): array
    {
        return [
            'confirmed' => __('The agent has talked with the supplier, and the supplier has agreed to make this order.'),
        ];
    }

    /**
     * Where a pending order is, furthest step wins: its container on the way to us, ready for a container (packed for
     * one or handed over), goods received at the agent's warehouse, the supplier making it, confirmed, submitted, or
     * still a draft.
     */
    public static function stageSql(): string
    {
        return "case
            when purchase_orders.state = 'in_process' then 'draft'
            when purchase_orders.delivery_state = 'dispatched' then 'on_the_way'
            when purchase_orders.delivery_state = 'ready_to_ship' or purchase_orders.handed_over_at is not null then 'ready_for_container'
            when exists (select 1 from agent_warehouse_stocks where agent_warehouse_stocks.purchase_order_id = purchase_orders.id and agent_warehouse_stocks.quantity > 0) then 'in_agent_warehouse'
            when purchase_orders.production_started_at is not null or purchase_orders.sample_approved_at is not null
                or purchase_orders.produced_at is not null or purchase_orders.qc_passed_at is not null then 'in_production'
            when purchase_orders.state = 'confirmed' then 'confirmed'
            else 'submitted'
        end";
    }

    /**
     * @return array<string, array{0: string, 1: ?string}> key => [label, upper limit of its age]
     */
    public static function ageBuckets(): array
    {
        return [
            '0_3'     => [__('0-3m'), "interval '3 months'"],
            '3_6'     => [__('3-6m'), "interval '6 months'"],
            '6_9'     => [__('6-9m'), "interval '9 months'"],
            '9_12'    => [__('9-12m'), "interval '12 months'"],
            'over_12' => [__('12+ months'), null],
        ];
    }

    /**
     * How long ago the order was sent to the supplier, drafts by their date, as one of the age bucket keys.
     */
    public static function ageSql(): string
    {
        $sentAt = 'coalesce(purchase_orders.submitted_at, purchase_orders.date)';

        return 'case '.collect(self::ageBuckets())
            ->filter(fn (array $bucket) => $bucket[1])
            ->map(fn (array $bucket, string $key) => "when {$sentAt} >= now() - {$bucket[1]} then '{$key}'")
            ->implode(' ')." else 'over_12' end";
    }

    /**
     * Supplier orders through an agent that are not cancelled, closed or received in our warehouses yet.
     */
    public static function wherePending($query): void
    {
        $query->whereNotNull('purchase_orders.agent_id')
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereIn('purchase_orders.state', [
                PurchaseOrderStateEnum::IN_PROCESS->value,
                PurchaseOrderStateEnum::SUBMITTED->value,
                PurchaseOrderStateEnum::CONFIRMED->value,
            ])
            ->whereRaw('('.PurchaseOrderStageEnum::progressSql().") <> 'arrived'");
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/AgentsPurchaseOrderStates',
            [
                'breadcrumbs'  => IndexAgents::make()->getBreadcrumbs('grp.supply-chain.agents.index', []),
                'title'        => __('Agents purchase orders'),
                'pageHead'     => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-people-arrows'],
                        'title' => __('Agents'),
                    ],
                    'title'         => __('Purchase orders'),
                    'subNavigation' => $this->getAgentsSubNavigation(),
                ],
                'currencyCode' => $this->group->currency->code,
                'stages'       => $data['stages'],
                'ages'         => $data['ages'],
                'agents'       => $data['agents'],
                'clients'      => $data['clients'],
                'figures'      => $data['figures'],
                'listRoute'    => ['name' => 'grp.supply-chain.agents.purchase_order_states.purchase_orders', 'parameters' => []],
            ]
        );
    }
}
