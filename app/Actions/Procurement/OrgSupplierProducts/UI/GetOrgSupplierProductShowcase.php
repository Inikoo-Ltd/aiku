<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Goods\Stock\UI\GetStockBarcodes;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockBarcodes;
use App\Actions\Procurement\AgentLabel\GetAgentOrgStocks;
use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductInfo;
use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductShowcase;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierProductShowcase
{
    use AsObject;
    use WithSupplierProductShowcase;
    use WithSupplierProductInfo;

    public function handle(OrgSupplierProduct $orgSupplierProduct): array
    {
        return array_merge(
            $this->getSupplierProductShowcase($orgSupplierProduct->supplierProduct, withSupplyChainLink: true, organisationId: $orgSupplierProduct->organisation_id),
            [
                'organisation' => [
                    'name'         => $orgSupplierProduct->organisation->name,
                    'code'         => $orgSupplierProduct->organisation->code,
                    'state'        => $orgSupplierProduct->state,
                    'is_available' => $orgSupplierProduct->is_available,
                ],
                'parties'      => array_values(array_filter([
                    $this->getOrgSupplierParty($orgSupplierProduct->orgSupplier),
                    $this->getOrgAgentParty($orgSupplierProduct->orgAgent),
                ])),
                'stats'        => $this->getProcurementStatsBoxes($orgSupplierProduct->stats),
                'supplierProductInfo' => $this->supplierProductInfo($orgSupplierProduct->supplierProduct),
                'internal_images'     => $this->getSupplierProductInternalImages(
                    $orgSupplierProduct->supplierProduct,
                    (bool) request()->user()?->authTo("procurement.{$orgSupplierProduct->organisation_id}.edit"),
                    $orgSupplierProduct
                ),
                'carton'              => $this->getCartonData($orgSupplierProduct),
            ],
            $this->getBarcodesData($orgSupplierProduct)
        );
    }

    /**
     * @return array{supplier_product_id: int, net_weight: int|null, gross_weight: int|null, update_route: array<string, mixed>|null}
     */
    private function getCartonData(OrgSupplierProduct $orgSupplierProduct): array
    {
        $supplierProduct     = $orgSupplierProduct->supplierProduct;
        $viewingOrganisation = request()->route('organisation');

        $canEdit = $viewingOrganisation instanceof Organisation
            && request()->user()?->authTo("procurement.{$viewingOrganisation->id}.edit")
            && ($orgSupplierProduct->organisation_id === $viewingOrganisation->id
                || ($viewingOrganisation->type === OrganisationTypeEnum::AGENT && $orgSupplierProduct->orgAgent?->agent_id === $viewingOrganisation->agent?->id));

        return [
            'supplier_product_id' => $supplierProduct->id,
            'net_weight'   => $supplierProduct->carton_net_weight,
            'gross_weight' => $supplierProduct->carton_weight,
            'update_route' => $canEdit
                ? [
                    'name'       => 'grp.models.org.org_supplier_product.carton_weights.update',
                    'parameters' => [
                        'organisation'       => $viewingOrganisation->id,
                        'orgSupplierProduct' => $orgSupplierProduct->id,
                    ],
                ]
                : null,
        ];
    }

    /**
     * The PDF label is printed from an SKO. In a shop organisation that is its own SKO of the product;
     * an agent holds no stock, so it prints the SKOs it buys for us, through its own label route since
     * it is not authorised in the organisations those SKOs belong to.
     *
     * Each SKO carries its own numbers, so what is shown is what that SKO's label prints. The master
     * stock is only a fallback when no SKO can be printed, and it never knows the unit EAN.
     *
     * @return array{barcodes: array<int, array<string, mixed>>, label_org_stocks: array<int, array<string, mixed>>}
     */
    private function getBarcodesData(OrgSupplierProduct $orgSupplierProduct): array
    {
        $viewingOrganisation = request()->route('organisation');
        $agent               = $viewingOrganisation instanceof Organisation && $viewingOrganisation->type === OrganisationTypeEnum::AGENT
            ? $viewingOrganisation->agent
            : null;

        $orgStocks = $agent
            ? $this->getAgentOrgStocks($orgSupplierProduct, $agent)
            : $this->getOwnOrgStocks($orgSupplierProduct);

        $labelOrgStocks = $orgStocks
            ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
            ->map(fn (OrgStock $orgStock) => [
                'id'                  => $orgStock->id,
                'code'                => $orgStock->code,
                'organisation_code'   => $orgStock->organisation->code,
                'barcodes'            => GetOrgStockBarcodes::run($orgStock),
                'label_options_route' => $agent
                    ? [
                        'name'       => 'grp.org.procurement.agent_labels.barcode_label_options',
                        'parameters' => [
                            'organisation' => $viewingOrganisation->slug,
                            'orgStock'     => $orgStock->id,
                        ],
                    ]
                    : [
                        'name'       => 'grp.json.warehouse.org_stock.label_options',
                        'parameters' => [
                            'warehouse' => $orgStock->organisation->warehouses->first()->slug,
                            'orgStock'  => $orgStock->id,
                        ],
                    ],
            ])
            ->values()
            ->all();

        $masterStock = $orgSupplierProduct->supplierProduct->stocks->first();

        return [
            'barcodes'         => $labelOrgStocks[0]['barcodes'] ?? ($masterStock ? GetStockBarcodes::run($masterStock) : []),
            'label_org_stocks' => $labelOrgStocks,
        ];
    }

    /**
     * @return Collection<int, OrgStock>
     */
    private function getAgentOrgStocks(OrgSupplierProduct $orgSupplierProduct, Agent $agent): Collection
    {
        return GetAgentOrgStocks::run($agent)
            ->whereIn('org_stocks.stock_id', $orgSupplierProduct->supplierProduct->stocks->pluck('id'))
            ->with('organisation')
            ->get();
    }

    /**
     * The SKOs linked to this supplier product, or else this organisation's SKOs of the same master stock.
     *
     * @return Collection<int, OrgStock>
     */
    private function getOwnOrgStocks(OrgSupplierProduct $orgSupplierProduct): Collection
    {
        $linkedOrgStocks = OrgStock::query()
            ->whereHas('orgSupplierProducts', fn ($query) => $query->where('org_supplier_products.id', $orgSupplierProduct->id))
            ->with('organisation.warehouses')
            ->get();

        $orgStocks = $linkedOrgStocks->isNotEmpty()
            ? $linkedOrgStocks
            : OrgStock::query()
                ->where('organisation_id', $orgSupplierProduct->organisation_id)
                ->whereIn('stock_id', $orgSupplierProduct->supplierProduct->stocks->pluck('id'))
                ->with('organisation.warehouses')
                ->get();

        return $orgStocks->filter(fn (OrgStock $orgStock) => $orgStock->organisation->warehouses->isNotEmpty());
    }
}
