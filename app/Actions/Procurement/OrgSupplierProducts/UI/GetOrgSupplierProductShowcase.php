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
use App\Actions\SupplyChain\SupplierProduct\UI\GetSupplierProductBarcodes;
use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductInfo;
use App\Actions\SupplyChain\SupplierProduct\UploadImagesToSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductShowcase;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierProductShowcase
{
    use AsObject;
    use WithSupplierProductShowcase;
    use WithSupplierProductInfo;

    public function handle(OrgSupplierProduct $orgSupplierProduct): array
    {
        $viewingOrganisation = request()->route('organisation');
        if ($viewingOrganisation instanceof Organisation && $viewingOrganisation->type === OrganisationTypeEnum::AGENT) {
            return $this->withoutLandedCosts(array_merge(
                $this->showcase($orgSupplierProduct),
                ['agent' => GetOrgSupplierProductAgentPanel::run($orgSupplierProduct, $viewingOrganisation, $this->canEditCarton($orgSupplierProduct))]
            ));
        }

        return $this->showcase($orgSupplierProduct);
    }

    /**
     * Agents see the cost agreed with the supplier, never what it costs us landed.
     */
    private function withoutLandedCosts(array $showcase): array
    {
        $showcase['costs'] = Arr::only($showcase['costs'], ['currency_code', 'unit_cost']);
        Arr::forget($showcase, ['supplierProductInfo.unit_expense', 'supplierProductInfo.extra_costs']);

        return $showcase;
    }

    private function showcase(OrgSupplierProduct $orgSupplierProduct): array
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
                    UploadImagesToSupplierProduct::canEditOrgSupplierProductPictures(request()->user(), $orgSupplierProduct),
                    $orgSupplierProduct
                ),
                'carton'              => $this->getCartonData($orgSupplierProduct),
            ],
            $this->getBarcodesData($orgSupplierProduct)
        );
    }

    private function canEditCarton(OrgSupplierProduct $orgSupplierProduct): bool
    {
        $viewingOrganisation = request()->route('organisation');

        return $viewingOrganisation instanceof Organisation
            && request()->user()?->authTo("procurement.{$viewingOrganisation->id}.edit")
            && ($orgSupplierProduct->organisation_id === $viewingOrganisation->id
                || ($viewingOrganisation->type === OrganisationTypeEnum::AGENT && $orgSupplierProduct->orgAgent?->agent_id === $viewingOrganisation->agent?->id));
    }

    /**
     * @return array{supplier_product_id: int, net_weight: int|null, gross_weight: int|null, update_route: array<string, mixed>|null}
     */
    private function getCartonData(OrgSupplierProduct $orgSupplierProduct): array
    {
        $supplierProduct     = $orgSupplierProduct->supplierProduct;
        $viewingOrganisation = request()->route('organisation');
        $canEdit             = $this->canEditCarton($orgSupplierProduct);

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
     * An agent sees the SKOs of every organisation it buys for, and the label carries the signature of
     * the SKO's organisation, so the page opens on the SKO of the organisation this product belongs to.
     * When that organisation has no SKO of it the page falls back to another organisation's and says
     * so, and the carton label still defaults to being commercialised by this product's organisation.
     *
     * @return array{barcodes: array<int, array<string, mixed>>, label_org_stocks: array<int, array<string, mixed>>, selected_label_org_stock_id: int|null, label_organisation: array{id: int, code: string, name: string, has_org_stock: bool}}
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

        $canEditCarton = $this->canEditCarton($orgSupplierProduct);

        $labelOrgStocks = $orgStocks
            ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
            ->map(fn (OrgStock $orgStock) => [
                'id'                  => $orgStock->id,
                'code'                => $orgStock->code,
                'organisation_id'     => $orgStock->organisation_id,
                'organisation_code'   => $orgStock->organisation->code,
                'barcodes'            => GetOrgStockBarcodes::run($orgStock, $orgSupplierProduct->supplierProduct->units_per_carton),
                'carton_barcode_update_route' => $canEditCarton && $orgStock->stock_id ? [
                    'name'       => 'grp.models.org.org_supplier_product.carton_barcode.update',
                    'parameters' => [
                        'organisation'       => $viewingOrganisation->id,
                        'orgSupplierProduct' => $orgSupplierProduct->id,
                        'stock'              => $orgStock->stock_id,
                    ],
                ] : null,
                'sko_barcode_update_route' => $this->getSkoBarcodeUpdateRoute($orgStock),
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

        $selectedLabelOrgStock = collect($labelOrgStocks)->firstWhere('organisation_id', $orgSupplierProduct->organisation_id)
            ?? $labelOrgStocks[0]
            ?? null;

        return [
            'barcodes'                    => $selectedLabelOrgStock['barcodes'] ?? ($masterStock ? GetStockBarcodes::run($masterStock, $orgSupplierProduct->supplierProduct->units_per_carton) : GetSupplierProductBarcodes::run($orgSupplierProduct->supplierProduct)),
            'label_org_stocks'            => $labelOrgStocks,
            'selected_label_org_stock_id' => $selectedLabelOrgStock['id'] ?? null,
            'label_organisation'          => [
                'id'            => $orgSupplierProduct->organisation_id,
                'code'          => $orgSupplierProduct->organisation->code,
                'name'          => $orgSupplierProduct->organisation->name,
                'has_org_stock' => collect($labelOrgStocks)->contains('organisation_id', $orgSupplierProduct->organisation_id),
            ],
        ];
    }

    /**
     * @return Collection<int, OrgStock>
     */
    private function getAgentOrgStocks(OrgSupplierProduct $orgSupplierProduct, Agent $agent): Collection
    {
        $stockIds = $orgSupplierProduct->supplierProduct->stocks->pluck('id')->all();

        return GetAgentOrgStocks::run($agent, stockIds: $stockIds)
            ->whereIn('org_stocks.stock_id', $stockIds)
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
