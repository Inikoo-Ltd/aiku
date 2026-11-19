<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\SetOrgStockPreferredSupplierProduct;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplierProducts\UI\IndexAgentSupplierProductSuppliers;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\SupplyChain\SupplierProduct\CopySupplierProductsToSupplier;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the Suppliers tab of its supplier product page, an agent makes another of its suppliers' products the
 * preferred one for the same SKU: in the one buying organisation selected, or in every buying organisation
 * of the page. An organisation whose SKO is not linked to that product is skipped and named.
 */
class SetAgentSupplierProductPreferred extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementEditAuthorisation;

    /**
     * @return array{updated: array<int, string>, skipped: array<int, string>}
     */
    public function handle(Organisation $agentOrganisation, SupplierProduct $supplierProduct, SupplierProduct $target, ?Organisation $only = null): array
    {
        $agent = $this->getOrganisationAgent($agentOrganisation);
        $stockIds = IndexAgentSupplierProductSuppliers::stockIds($supplierProduct);

        if (!$agent || $target->supplier?->agent_id !== $agent->id || !IndexAgentSupplierProductSuppliers::stockIds($target)->intersect($stockIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['supplier_product_id' => __('This product is not supplied by one of the agent\'s suppliers for the same SKU.')]);
        }

        if (!CopySupplierProductsToSupplier::canBecomePreferred($target)) {
            throw ValidationException::withMessages(['supplier_product_id' => __('A discontinued or unavailable product, or one of an inactive supplier, can not be made preferred.')]);
        }

        $organisations = self::buyingOrganisations($supplierProduct, $agent)
            ->when($only, fn ($organisations) => $organisations->where('id', $only->id));

        $result = ['updated' => [], 'skipped' => []];

        foreach ($organisations as $organisation) {
            $links = OrgStockHasOrgSupplierProduct::query()
                ->join('org_stocks', 'org_stocks.id', 'org_stock_has_org_supplier_products.org_stock_id')
                ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
                ->whereIn('org_stocks.stock_id', $stockIds)
                ->where('org_stocks.organisation_id', $organisation->id)
                ->where('org_supplier_products.supplier_product_id', $target->id)
                ->where('org_stock_has_org_supplier_products.status', true)
                ->select('org_stock_has_org_supplier_products.*')
                ->with(['orgStock', 'orgSupplierProduct'])
                ->get();

            if ($links->isEmpty()) {
                $result['skipped'][] = $organisation->code;
                continue;
            }

            $links->each(fn (OrgStockHasOrgSupplierProduct $link) => SetOrgStockPreferredSupplierProduct::make()->handle($link->orgStock, $link->orgSupplierProduct));
            $result['updated'][] = $organisation->code;
        }

        return $result;
    }

    /**
     * @return Collection<int, Organisation>
     */
    public static function buyingOrganisations(SupplierProduct $supplierProduct, Agent $agent): Collection
    {
        return OrgSupplierProduct::where('supplier_product_id', $supplierProduct->id)
            ->whereIn('org_agent_id', OrgAgent::where('agent_id', $agent->id)->select('id'))
            ->with('organisation')
            ->orderBy('organisation_id')
            ->get()
            ->pluck('organisation')
            ->unique('id')
            ->values();
    }

    public function rules(): array
    {
        return [
            'supplier_product_id' => ['required', 'integer', 'exists:supplier_products,id'],
            'organisation_id'     => ['sometimes', 'nullable', 'integer', 'exists:organisations,id'],
        ];
    }

    /**
     * @return array{updated: array<int, string>, skipped: array<int, string>, message: string}
     */
    public function asController(Organisation $organisation, SupplierProduct $supplierProduct, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        $result = $this->handle(
            $organisation,
            $supplierProduct,
            SupplierProduct::findOrFail($this->validatedData['supplier_product_id']),
            isset($this->validatedData['organisation_id']) ? Organisation::find($this->validatedData['organisation_id']) : null
        );

        return $result + ['message' => $this->message($result)];
    }

    /**
     * @param  array{updated: array<int, string>, skipped: array<int, string>}  $result
     */
    private function message(array $result): string
    {
        return trim(implode(' ', array_filter([
            $result['updated'] ? __('Preferred in :organisations.', ['organisations' => implode(', ', $result['updated'])]) : null,
            $result['skipped'] ? __('Not changed in :organisations: this product is not linked to their SKO.', ['organisations' => implode(', ', $result['skipped'])]) : null,
        ])));
    }
}
