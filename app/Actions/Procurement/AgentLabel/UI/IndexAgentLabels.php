<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\AgentLabel\GetAgentOrgStocks;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Production\Artefact\Label\DownloadArtefactLabelPdf;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Production\ArtefactLabel;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The labels an agent prints: the published ones of every SKO it buys for us.
 */
class IndexAgentLabels extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    /**
     * @return array{org_stocks: array<int, array<string, mixed>>, number_without_labels: int}
     */
    public function handle(Agent $agent): array
    {
        $isPublished = fn ($query) => $query->where('state', ArtefactLabelStateEnum::PUBLISHED);

        // ponytail: one unpaginated list, only SKOs with a published label; paginate if an agent reaches a few hundred
        $orgStocks = GetAgentOrgStocks::run($agent)
            ->whereHas('labels', $isPublished)
            ->with(['organisation', 'labels' => $isPublished])
            ->orderBy('org_stocks.code')
            ->get();

        return [
            'org_stocks'            => $orgStocks->map(fn (OrgStock $orgStock) => [
                'id'           => $orgStock->id,
                'code'         => $orgStock->code,
                'name'         => $orgStock->name,
                'organisation' => $orgStock->organisation->name,
                'labels'       => $orgStock->labels->map(fn (ArtefactLabel $label) => [
                    'id'          => $label->id,
                    'name'        => $label->name,
                    'run_sources' => DownloadArtefactLabelPdf::getRunSources($label),
                    'pdf_url'     => route('grp.org.procurement.agent_labels.pdf', [
                        'organisation' => $this->organisation->slug,
                        'orgStock'     => $orgStock->id,
                        'label'        => $label->id,
                    ]),
                ])->values()->all(),
            ])->all(),
            'number_without_labels' => GetAgentOrgStocks::run($agent)->whereDoesntHave('labels', $isPublished)->count(),
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
            'Org/Procurement/AgentLabels',
            [
                'title'       => __('Labels'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Labels'),
                        'icon'  => 'fal fa-tags'
                    ],
                    'title' => __('Labels to print'),
                ],
                'data'        => $data,
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Labels'),
                        'icon'  => 'fal fa-tags',
                        'route' => [
                            'name'       => 'grp.org.procurement.agent_labels.index',
                            'parameters' => ['organisation' => $routeParameters['organisation']],
                        ],
                    ],
                ],
            ],
        );
    }
}
