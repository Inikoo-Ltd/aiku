<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-20 10:05
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\PackingSpec\UI;

use App\Actions\SupplyChain\PackingSpec\StorePackingSpec;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Enums\SupplyChain\PackingSpec\PackingSpecFateEnum;
use App\Enums\SupplyChain\PackingSpec\PackingSpecLevelEnum;
use App\Enums\SupplyChain\PackingSpec\PackingSpecPackedByEnum;
use App\Enums\SupplyChain\PackingSpec\PackingSpecRoleEnum;
use App\Enums\SupplyChain\PackingSpec\PackingSpecStatusEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Goods\PackagingComponent;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\PackingSpecLine;
use App\Models\SupplyChain\SupplierProductPackingSpec;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The Packing tab of a supplier product: the live packing spec with its photo and weight check, the versions before
 * it, and what the agent needs to declare a new one.
 */
class GetOrgSupplierProductPackingSpec
{
    use AsAction;

    public const int COPY_CANDIDATES = 500;

    public const int PURCHASE_ORDERS = 50;

    /**
     * @return array<string, mixed>
     */
    public function handle(OrgSupplierProduct $orgSupplierProduct, Organisation $viewer, User $user): array
    {
        $supplierProduct = $orgSupplierProduct->supplierProduct;
        $isAgent         = $viewer->type === OrganisationTypeEnum::AGENT;
        $agentId         = $isAgent ? $viewer->agent?->id : null;
        $ownsProduct     = $orgSupplierProduct->organisation_id === $viewer->id || ($agentId && $orgSupplierProduct->orgAgent?->agent_id === $agentId);
        $canEdit         = $ownsProduct && $user->authTo("procurement.$viewer->id.edit");
        $canReview       = !$isAgent && $canEdit;

        $versions = $supplierProduct->packingSpecs()->with(['lines.packagingComponent', 'submittedBy', 'verifiedBy', 'returnedBy'])->get();
        $live     = $versions->first(fn (SupplierProductPackingSpec $spec) => in_array($spec->status, PackingSpecStatusEnum::live()));

        $specRoute = fn (string $action, SupplierProductPackingSpec $spec) => [
            'name'       => "grp.models.org.packing_spec.$action",
            'parameters' => ['organisation' => $viewer->id, 'packingSpec' => $spec->id],
        ];

        return [
            'is_agent'         => $isAgent,
            'tolerance_pct'    => SupplierProductPackingSpec::WEIGHT_TOLERANCE_PCT,
            'supplier_product' => [
                'code'              => $supplierProduct->code,
                'name'              => $supplierProduct->name,
                'units_per_pack'    => $supplierProduct->units_per_pack,
                'units_per_carton'  => $supplierProduct->units_per_carton,
                'carton_weight'     => $supplierProduct->carton_weight,
                'carton_net_weight' => $supplierProduct->carton_net_weight,
                'unit_net_weight_g' => round((float)$supplierProduct->tradeUnits->sum(fn ($tradeUnit) => (float)$tradeUnit->net_weight * (float)($tradeUnit->pivot->quantity ?? 1)), 1) ?: null,
                'carton_dimensions' => $supplierProduct->carton_dimensions,
            ],
            'live'     => $live ? $this->spec($live, $canReview, $specRoute) : null,
            'versions' => $versions->map(fn (SupplierProductPackingSpec $spec) => [
                'id'             => $spec->id,
                'version'        => $spec->version,
                'status'         => $spec->status->value,
                'status_label'   => PackingSpecStatusEnum::labels()[$spec->status->value],
                'effective_from' => $spec->effective_from->toDateString(),
                'submitted_by'   => $spec->submittedBy?->contact_name ?: $spec->submittedBy?->username,
                'submitted_at'   => $spec->submitted_at?->toIso8601String(),
                'lines'          => $spec->lines->count(),
                'packaging_g'    => $spec->packaging_weight_g === null ? null : (float)$spec->packaging_weight_g,
                'return_reason'  => $spec->return_reason,
                'pdf_url'        => route('grp.models.org.packing_spec.pdf', ['organisation' => $viewer->id, 'packingSpec' => $spec->id]),
            ])->values(),
            'dropdown' => PackagingComponent::where('group_id', $supplierProduct->group_id)
                ->where('is_dropdown_item', true)
                ->orderBy('dropdown_position')
                ->get()
                ->map(fn (PackagingComponent $component) => [
                    'id'                => $component->id,
                    'key'               => $component->dropdown_key,
                    'label'             => $component->dropdownLabel(),
                    'labels'            => $component->dropdown_labels,
                    'default_role'      => $component->default_role?->value,
                    'material'          => PackagingMaterialCategoryEnum::labels()[$component->material_category->value],
                    'is_epr_reportable' => $component->is_epr_reportable,
                    'needs_review'      => $component->needs_review,
                ])->values(),
            'options' => [
                'levels'    => PackingSpecLevelEnum::labels(),
                'roles'     => PackingSpecRoleEnum::labels(),
                'packed_by' => PackingSpecPackedByEnum::labels(),
                'fates'     => PackingSpecFateEnum::labels(),
            ],
            'copy_candidates' => $canEdit ? $this->copyCandidates($orgSupplierProduct, $isAgent ? $agentId : null) : [],
            'instructions'    => $supplierProduct->packing_instructions ?? [],
            'instructions_route' => $canEdit ? [
                'name'       => 'grp.models.org.org_supplier_product.packing_instructions.update',
                'parameters' => ['organisation' => $viewer->id, 'orgSupplierProduct' => $orgSupplierProduct->id],
            ] : null,
            'purchase_orders' => $this->purchaseOrders($supplierProduct->id, $viewer, $agentId),
            'store_route'     => $canEdit ? [
                'name'       => 'grp.models.org.org_supplier_product.packing_spec.store',
                'parameters' => ['organisation' => $viewer->id, 'orgSupplierProduct' => $orgSupplierProduct->id],
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(SupplierProductPackingSpec $spec, bool $canReview, callable $specRoute): array
    {
        $photo = StorePackingSpec::make()->photoOf($spec);

        return [
            'id'                             => $spec->id,
            'version'                        => $spec->version,
            'status'                         => $spec->status->value,
            'status_label'                   => PackingSpecStatusEnum::labels()[$spec->status->value],
            'effective_from'                 => $spec->effective_from->toDateString(),
            'packed_by'                      => $spec->packed_by->value,
            'carton_length_mm'               => $spec->carton_length_mm,
            'carton_width_mm'                => $spec->carton_width_mm,
            'carton_height_mm'               => $spec->carton_height_mm,
            'declared_carton_gross_weight_g' => $spec->declared_carton_gross_weight_g,
            'measured_carton_gross_weight_g' => $spec->measured_carton_gross_weight_g,
            'packaging_weight_g'             => $spec->packaging_weight_g === null ? null : (float)$spec->packaging_weight_g,
            'weight_check_deviation_pct'     => $spec->weight_check_deviation_pct === null ? null : (float)$spec->weight_check_deviation_pct,
            'needs_review'                   => $spec->needs_review,
            'review_reason'                  => $spec->review_reason,
            'materials_weighed'              => $spec->materials_weighed,
            'return_reason'                  => $spec->return_reason,
            'returned_at'                    => $spec->returned_at?->toIso8601String(),
            'returned_by'                    => $spec->returnedBy?->contact_name ?: $spec->returnedBy?->username,
            'verified_at'                    => $spec->verified_at?->toIso8601String(),
            'verified_by'                    => $spec->verifiedBy?->contact_name ?: $spec->verifiedBy?->username,
            'photo'                          => $photo ? [
                'url'          => route('grp.media.download', [$photo->ulid, 'inline' => 1]),
                'download_url' => route('grp.media.download', [$photo->ulid]),
            ] : null,
            'lines' => $spec->lines->map(fn (PackingSpecLine $line) => [
                'id'                     => $line->id,
                'packaging_component_id' => $line->packaging_component_id,
                'label'                  => $line->packagingComponent->dropdownLabel(),
                'level'                  => $line->level->value,
                'role'                   => $line->role->value,
                'quantity'               => (float)$line->quantity,
                'weight_g_each'          => $line->weight_g_each === null ? null : (float)$line->weight_g_each,
                'declared_weight_g_each' => $line->declared_weight_g_each === null ? null : (float)$line->declared_weight_g_each,
                'weight_is_measured'     => $line->weight_is_measured,
                'fate_at_our_warehouse'  => $line->fate_at_our_warehouse->value,
                'is_epr_reportable'      => $line->is_epr_reportable,
                'description'            => $line->description,
                'grams_per_carton'       => round($line->gramsPerCarton($spec->unitsPerCarton(), $spec->packsPerCarton()), 1),
            ])->values(),
            'return_route' => $canReview && in_array($spec->status, [PackingSpecStatusEnum::SUBMITTED, PackingSpecStatusEnum::VERIFIED]) ? $specRoute('return', $spec) : null,
            'verify_route' => $canReview && in_array($spec->status, [PackingSpecStatusEnum::SUBMITTED, PackingSpecStatusEnum::RETURNED]) ? $specRoute('verify', $spec) : null,
        ];
    }

    /**
     * The open purchase orders with this product the viewer may print packing instructions for: the agent's orders for
     * an agent organisation, the organisation's own otherwise. Newest first.
     *
     * @return list<array{label: string, url: string}>
     */
    private function purchaseOrders(int $supplierProductId, Organisation $viewer, ?int $agentId): array
    {
        $routeName = $agentId ? 'grp.org.agent.purchase_orders.packing_instructions' : 'grp.org.procurement.purchase_orders.packing_instructions';

        return PurchaseOrderTransaction::query()
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
            ->where('purchase_order_transactions.supplier_product_id', $supplierProductId)
            ->where('purchase_order_transactions.quantity_ordered', '>', 0)
            ->where('purchase_order_transactions.state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED)
            ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::live())
            ->when(
                $agentId,
                fn ($query) => $query->where('purchase_orders.agent_id', $agentId),
                fn ($query) => $query->where('purchase_orders.organisation_id', $viewer->id)
            )
            ->orderByDesc('purchase_orders.date')
            ->limit(self::PURCHASE_ORDERS)
            ->get(['purchase_order_transactions.id', 'purchase_order_transactions.quantity_ordered', 'purchase_orders.slug', 'purchase_orders.reference', 'purchase_orders.state as purchase_order_state', 'organisations.code as organisation_code'])
            ->map(fn ($row) => [
                'label' => sprintf('%s · %s · %s %s · %s', $row->reference, $row->organisation_code, rtrim(rtrim(number_format((float)$row->quantity_ordered, 3, '.', ''), '0'), '.'), __('units'), PurchaseOrderStateEnum::labels()[$row->purchase_order_state] ?? $row->purchase_order_state),
                'url'   => route($routeName, ['organisation' => $viewer->slug, 'purchaseOrder' => $row->slug, 'transaction' => $row->id]),
            ])
            ->all();
    }

    /**
     * Other products of the same supplier the agent (or the organisation) buys, for "same packing as".
     *
     * @return list<array{id: int, code: string, name: string|null, has_spec: bool}>
     */
    private function copyCandidates(OrgSupplierProduct $orgSupplierProduct, ?int $agentId): array
    {
        $supplierId = $orgSupplierProduct->supplierProduct->supplier_id;
        if (!$supplierId) {
            return [];
        }

        return OrgSupplierProduct::query()
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->where('supplier_products.supplier_id', $supplierId)
            ->whereNull('supplier_products.deleted_at')
            ->where('supplier_products.id', '!=', $orgSupplierProduct->supplier_product_id)
            ->when(
                $agentId,
                fn ($query) => $query->whereIn('org_supplier_products.org_agent_id', fn ($query) => $query->select('id')->from('org_agents')->where('agent_id', $agentId)),
                fn ($query) => $query->where('org_supplier_products.organisation_id', $orgSupplierProduct->organisation_id)
            )
            ->selectRaw('DISTINCT ON (supplier_products.code, supplier_products.id) org_supplier_products.id, supplier_products.code, supplier_products.name')
            ->selectRaw('EXISTS (SELECT 1 FROM supplier_product_packing_specs s WHERE s.supplier_product_id = supplier_products.id AND s.status IN ('.PackingSpecStatusEnum::liveSql().')) AS has_spec')
            ->orderBy('supplier_products.code')
            ->orderBy('supplier_products.id')
            ->limit(self::COPY_CANDIDATES)
            ->get()
            ->map(fn ($row) => ['id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'has_spec' => (bool)$row->has_spec])
            ->all();
    }
}
