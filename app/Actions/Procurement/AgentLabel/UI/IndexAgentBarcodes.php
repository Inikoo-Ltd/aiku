<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel\UI;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockBarcodes;
use App\Actions\OrgAction;
use App\Actions\Procurement\AgentLabel\GetAgentOrgStocks;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The barcodes of every SKO an agent buys for us, so it can print the unit, SKO or carton label of any of them.
 */
class IndexAgentBarcodes extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function handle(Agent $agent, ?string $search = null): LengthAwarePaginator
    {
        return GetAgentOrgStocks::run($agent)
            ->with(['organisation:id,code', 'stock:id,carton_barcode,gross_weight', 'tradeUnits'])
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->whereAnyWordStartWith('org_stocks.code', $search)
                ->orWhereAnyWordStartWith('org_stocks.name', $search)
                ->orWhere('org_stocks.barcode', $search)
                ->orWhere('org_stocks.unit_barcode', $search)))
            ->orderBy('org_stocks.code')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (OrgStock $orgStock) => [
                'id'                  => $orgStock->id,
                'code'                => $orgStock->code,
                'name'                => $orgStock->name,
                'organisation_code'   => $orgStock->organisation->code,
                'barcodes'            => GetOrgStockBarcodes::run($orgStock),
                'label_options_route' => [
                    'name'       => 'grp.org.agent.agent_labels.barcode_label_options',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'orgStock'     => $orgStock->id,
                    ],
                ],
            ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent, $request->string('search')->trim()->value() ?: null);
    }

    public function htmlResponse(LengthAwarePaginator $orgStocks, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentBarcodes',
            [
                'title'       => __('Barcodes'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Barcodes'),
                                'route' => [
                                    'name'       => 'grp.org.agent.agent_barcodes.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Barcodes'),
                        'icon'  => 'fal fa-barcode',
                    ],
                    'title' => __('Barcodes'),
                ],
                'search'      => $request->string('search')->value(),
                'data'        => $orgStocks,
            ]
        );
    }
}
