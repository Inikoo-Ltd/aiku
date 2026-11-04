<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 11:20
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The PO journey board for an agent's own staff: every order the agent handles, stage by stage, opening the agent's
 * copy of the order. The agent marks only the stages that are its own work (production, packing, hand over).
 */
class ShowAgentPurchaseOrdersOverview extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private Agent $agent;

    public function handle(Agent $agent, ActionRequest $request): array
    {
        $data = ShowSupplyChainPurchaseOrderJourney::make()->handle($request, $agent);

        $data['ribbons'] = array_map(fn (array $ribbon) => [
            ...$ribbon,
            'agent_name' => null,
            'agent_slug' => null,
            'route'      => [
                'name'       => 'grp.org.agent.purchase_orders.show',
                'parameters' => ['organisation' => $this->organisation->slug, 'purchaseOrder' => $ribbon['slug']],
            ],
            'segments'   => array_map(fn (array $segment) => [
                ...$segment,
                'mark_column' => $segment['mark_column'] && PurchaseOrderStageEnum::agentMayMark($segment['mark_column']) ? $segment['mark_column'] : null,
            ], $ribbon['segments']),
        ], $data['ribbons']);

        return $data;
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        return [];
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        $overview = null;
        $part     = function (string $key) use (&$overview, $request) {
            $overview ??= $this->handle($this->agent, $request);

            return $overview[$key];
        };

        return Inertia::render(
            'SupplyChain/SupplyChainPurchaseOrderJourney',
            [
                'title'         => __('PO Overview'),
                'breadcrumbs'   => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('PO Overview'),
                                'route' => [
                                    'name'       => 'grp.org.agent.purchase_orders.overview',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'      => [
                    'icon'  => [
                        'title' => __('PO Overview'),
                        'icon'  => ['fal', 'fa-route'],
                    ],
                    'title' => __('PO Overview'),
                ],
                'groupCurrency' => $this->agent->group->currency->code,
                'canMark'       => $this->canEdit,
                'stages'        => array_map(fn (array $stage) => [
                    ...$stage,
                    'markable' => $stage['markable'] && PurchaseOrderStageEnum::agentMayMark((string) PurchaseOrderJourneyStageEnum::from($stage['key'])->markColumn()),
                ], ShowSupplyChainPurchaseOrderJourney::make()->stages()),
                'filters'       => Inertia::defer(fn () => $part('filters'), 'journey'),
                'active'        => Inertia::defer(fn () => $part('active'), 'journey'),
                'summary'       => Inertia::defer(fn () => $part('summary'), 'journey'),
                'blockages'     => Inertia::defer(fn () => $part('blockages'), 'journey'),
                'quickStats'    => Inertia::defer(fn () => $part('quickStats'), 'journey'),
                'ribbons'       => Inertia::defer(fn () => $part('ribbons'), 'journey'),
                'pagination'    => Inertia::defer(fn () => $part('pagination'), 'journey'),
            ]
        );
    }
}
