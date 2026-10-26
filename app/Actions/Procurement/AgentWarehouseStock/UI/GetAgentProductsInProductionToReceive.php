<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 14:55
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The lines still in production of one supplier or one purchase order, for the receive dialog of the In production page
 * to match the codes typed or pasted in.
 */
class GetAgentProductsInProductionToReceive extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    /**
     * @return list<array{id: int, code: string, name: ?string, supplier_code: ?string, organisation_code: string, purchase_order_reference: string, quantity_ordered: float, quantity: float, units_per_sko: float}>
     */
    public function handle(Agent $agent, array $scope): array
    {
        return IndexAgentProductsInProduction::inProductionLines($agent)
            ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->leftJoin('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->join('organisations', 'organisations.id', 'purchase_order_transactions.organisation_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
            ->when(isset($scope['supplier']), fn ($query) => $query->where('supplier_products.supplier_id', $scope['supplier']))
            ->when(isset($scope['purchase_order']), fn ($query) => $query->where('production_orders.id', $scope['purchase_order']))
            ->orderBy('production_orders.id')
            ->orderBy('purchase_order_transactions.id')
            ->select([
                'purchase_order_transactions.id',
                'purchase_order_transactions.quantity_ordered',
                DB::raw(IndexAgentProductsInProduction::remainingQuantitySql($agent).' as quantity'),
                'supplier_products.code',
                'supplier_products.name',
                DB::raw('coalesce(nullif(org_stocks.packed_in, 0), supplier_products.units_per_pack, 1) as units_per_sko'),
                'suppliers.code as supplier_code',
                'organisations.code as organisation_code',
                'production_orders.reference as purchase_order_reference',
            ])
            ->get()
            ->map(fn (PurchaseOrderTransaction $line) => [
                'id'                       => $line->id,
                'code'                     => $line->code,
                'name'                     => $line->name,
                'supplier_code'            => $line->supplier_code,
                'organisation_code'        => strtoupper($line->organisation_code),
                'purchase_order_reference' => $line->purchase_order_reference,
                'quantity_ordered'         => (float) $line->quantity_ordered,
                'quantity'                 => (float) $line->quantity,
                'units_per_sko'            => (float) $line->units_per_sko,
            ])
            ->all();
    }

    public function rules(): array
    {
        return [
            'supplier'       => ['required_without:purchase_order', 'nullable', 'integer'],
            'purchase_order' => ['required_without:supplier', 'nullable', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agent, array_filter($this->validatedData));
    }
}
