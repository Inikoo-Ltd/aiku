<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel\UI;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockBarcodes;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockUnifiedLabelData;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\OrgAction;
use App\Actions\Procurement\AgentLabel\PdfAgentOrgStockBarcodeLabels;
use App\Actions\Procurement\AgentLabel\GetAgentOrgStocks;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Helpers\Media;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    private ?Agent $agent = null;

    public function handle(Agent $agent, ?string $search = null, ?string $organisationSlug = null): LengthAwarePaginator
    {
        $orgStocks = GetAgentOrgStocks::run($agent)
            ->with(['organisation:id,code,slug', 'stock:id,carton_barcode,gross_weight,image_id', 'stock.supplierProducts:supplier_products.id,supplier_products.units_per_carton,supplier_products.image_id', 'tradeUnits'])
            ->when($organisationSlug, fn ($query) => $query->whereHas('organisation', fn ($query) => $query->where('slug', $organisationSlug)))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->whereAnyWordStartWith('org_stocks.code', $search)
                ->orWhereAnyWordStartWith('org_stocks.name', $search)
                ->orWhere('org_stocks.barcode', $search)
                ->orWhere('org_stocks.unit_barcode', $search)))
            ->orderBy('org_stocks.code')
            ->paginate(50)
            ->withQueryString();

        $images = Media::whereIn('id', $orgStocks->getCollection()->map(fn (OrgStock $orgStock) => $this->imageId($orgStock))->filter()->unique())->get()->keyBy('id');

        return $orgStocks->through(function (OrgStock $orgStock) use ($images) {
            $image = $images->get($this->imageId($orgStock));

            return [
                'id'                  => $orgStock->id,
                'code'                => $orgStock->code,
                'name'                => $orgStock->name,
                'organisation_code'   => $orgStock->organisation->code,
                'image_thumbnail'     => $image ? GetPictureSources::run($image->getImage()->resize(80, 80)) : null,
                'image_full'          => $image?->fullResolutionUrls(),
                'barcodes'            => GetOrgStockBarcodes::run($orgStock),
                'label_options_route' => [
                    'name'       => 'grp.org.agent.agent_labels.barcode_label_options',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'orgStock'     => $orgStock->id,
                    ],
                ],
            ];
        });
    }

    /**
     * The SKO's own picture, else the first of its trade units, else the first of its supplier products.
     */
    private function imageId(OrgStock $orgStock): ?int
    {
        return $orgStock->stock?->image_id
            ?? $orgStock->tradeUnits->firstWhere('image_id', '!=', null)?->image_id
            ?? $orgStock->stock?->supplierProducts->firstWhere('image_id', '!=', null)?->image_id;
    }

    /**
     * @return array<int, array{label: string, value: string, count: int, tooltip: string}>
     */
    public function organisationOptions(Agent $agent): array
    {
        return GetAgentOrgStocks::run($agent)
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->groupBy('organisations.slug', 'organisations.code', 'organisations.name', 'countries.name')
            ->orderBy('organisations.code')
            ->select('organisations.slug', 'organisations.code', 'organisations.name', 'countries.name as country_name', DB::raw('count(*) as total'))
            ->toBase()
            ->get()
            ->map(fn ($organisation) => [
                'label'   => strtoupper($organisation->code),
                'value'   => $organisation->slug,
                'count'   => (int) $organisation->total,
                'tooltip' => $organisation->country_name ? "$organisation->name ($organisation->country_name)" : $organisation->name,
            ])
            ->all();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $this->agent = $this->getOrganisationAgent($organisation);
        abort_unless($this->agent, 404);

        return $this->handle(
            $this->agent,
            $request->string('search')->trim()->value() ?: null,
            $request->string('organisation')->trim()->value() ?: null
        );
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
                'organisation_filter' => [
                    'selected' => $request->string('organisation')->value() ?: null,
                    'options'  => $this->organisationOptions($this->agent),
                ],
                'bulk_labels' => [
                    'route'          => [
                        'name'       => 'grp.org.agent.agent_labels.barcode_labels',
                        'parameters' => ['organisation' => $this->organisation->slug],
                    ],
                    'check_route'    => [
                        'name'       => 'grp.org.agent.agent_labels.barcode_labels.check',
                        'parameters' => ['organisation' => $this->organisation->slug],
                    ],
                    'sizes'          => GetOrgStockLabelOptions::make()->getSizes(),
                    'default_size'   => PdfOrgStockLabel::DEFAULT_SIZE,
                    'batch_pattern'  => trim(GetOrgStockUnifiedLabelData::BATCH_PATTERN, '/'),
                    'max_org_stocks' => PdfAgentOrgStockBarcodeLabels::MAX_ORG_STOCKS,
                ],
                'data'        => $orgStocks,
            ]
        );
    }
}
