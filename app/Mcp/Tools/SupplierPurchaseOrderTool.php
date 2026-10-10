<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Procurement\AgentOrder\ResolveAgentOrderReference;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * The purchase order to an external supplier, placed by an assistant the way a buyer does it on the
 * supplier page: one order being prepared per supplier, lines added from the supplier's products,
 * then submitted. A supplier bought through an agent joins the agent's order as it does in the UI.
 * Placing needs can_use_mcp_place_orders.
 */
#[Description('Purchase orders to external suppliers (not partners or the manufacturing hub), including suppliers bought through an agent. Lines are supplier product or SKO codes with a quantity in units; quantities are rounded up to whole cartons and to the supplier\'s minimum carton order. Without place it previews the order: supplier, agent and agent order, the supplier\'s currency, each line with units, cartons, unit cost and line cost in that currency, the order already being prepared for that supplier with its lines, and the total that placing submits in the buying organisation\'s currency. With place=true, for users enrolled to place orders, it adds the lines to the order being prepared (or a new one) and submits it; it is not emailed to the supplier, send it from the purchase order page. It cannot be undone from the AI changes log. Only place after showing the preview and the user confirmed the supplier, lines and quantities in their own words, passing their request text.')]
class SupplierPurchaseOrderTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'     => ['required', 'string'],
            'supplier'         => ['required', 'string'],
            'lines'            => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.code'     => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'place'            => ['sometimes', 'boolean'],
            'request_text'     => ['required_if:place,true', 'string', 'max:4000'],
        ]);

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();
        if (!$organisation || !$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_VIEW->value, $organisation))) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $supplierCode = strtolower((string) $request->string('supplier'));
        $orgSupplier  = OrgSupplier::where('organisation_id', $organisation->id)
            ->where('status', true)
            ->whereHas('supplier', fn ($query) => $query->whereRaw('(lower(code) = ? or lower(slug) = ?)', [$supplierCode, $supplierCode]))
            ->with(['supplier.currency', 'orgAgent.agent.organisation'])
            ->get()
            ->sortByDesc(fn (OrgSupplier $orgSupplier) => strtolower($orgSupplier->supplier->code) === $supplierCode)
            ->first();
        if (!$orgSupplier) {
            return Response::error("{$organisation->code} buys from no active supplier {$request->string('supplier')}. Use the supplier code.");
        }

        $wanted  = [];
        $refused = [];
        foreach ($request->get('lines') as $line) {
            $matches = $this->orgSupplierProducts($orgSupplier, $line['code']);
            if ($matches->count() !== 1) {
                $refused[] = $matches->isEmpty()
                    ? "{$line['code']}: not an available product of {$orgSupplier->supplier->code}"
                    : "{$line['code']}: matches several products (".$matches->map(fn (OrgSupplierProduct $orgSupplierProduct) => $orgSupplierProduct->supplierProduct->code)->implode(', ').'), use the supplier product code';
                continue;
            }
            $orgSupplierProduct = $matches->first();
            if ($orgSupplierProduct->supplierProduct->currency_id !== $orgSupplier->supplier->currency_id) {
                $refused[] = "{$line['code']}: priced in another currency than {$orgSupplier->supplier->code}'s orders, add it on the purchase order page";
                continue;
            }
            $wanted[$orgSupplierProduct->id] ??= ['org_supplier_product' => $orgSupplierProduct, 'quantity' => 0.0];
            $wanted[$orgSupplierProduct->id]['quantity'] += (float) $line['quantity'];
        }

        $beingPrepared = $orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest()->first();
        if ($beingPrepared) {
            $alreadyOrdered = $beingPrepared->purchaseOrderTransactions()->whereIn('org_supplier_product_id', array_keys($wanted))->pluck('quantity_ordered', 'org_supplier_product_id');
            foreach ($alreadyOrdered as $orgSupplierProductId => $quantity) {
                $refused[] = $wanted[$orgSupplierProductId]['org_supplier_product']->supplierProduct->code.": already on {$beingPrepared->reference} with ".(float) $quantity.' units, change it on the purchase order page or leave it out';
            }
        }

        if ($refused) {
            return Response::error(implode('. ', $refused).'. Nothing was changed.');
        }

        $lines = array_values(array_map(fn (array $line) => $this->line($line['org_supplier_product'], $line['quantity']), $wanted));

        if (!$request->boolean('place')) {
            return Response::json($this->preview($orgSupplier, $lines, $beingPrepared));
        }

        if (!$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_EDIT->value, $organisation))) {
            return Response::error("This user cannot edit procurement in {$organisation->code}. Nothing was changed.");
        }

        if ($refusal = $this->orderPlacingRefusal($request)) {
            return Response::error($refusal);
        }

        try {
            $purchaseOrder = $this->recordChange(
                $request,
                McpChangeTypeEnum::PLACED_ORDER,
                "Purchase order of {$organisation->code} to {$orgSupplier->supplier->code}",
                ['org_supplier_id' => $orgSupplier->id],
                fn () => $this->place($orgSupplier, $lines, $request->user()->id),
                ['organisation' => $organisation->code]
            );
        } catch (ValidationException $exception) {
            return Response::error(collect($exception->errors())->flatten()->implode(' ').' Nothing was sent.');
        }

        return Response::json([
            'changed'        => true,
            'order_log_id'   => $this->mcpChange?->id,
            'purchase_order' => $purchaseOrder->reference,
            'agent_order'    => $purchaseOrder->agent_order_reference,
            'state'          => $purchaseOrder->state->value,
            'lines'          => $purchaseOrder->purchaseOrderTransactions()->count(),
            'total'          => $purchaseOrder->organisation->currency->code.' '.number_format((float) $purchaseOrder->purchaseOrderTransactions()->sum('org_net_amount'), 2),
            'note'           => $purchaseOrder->agent_order_reference
                ? "Submitted, not emailed: send it to the agent from Procurement > Agents > the agent > Agent orders > {$purchaseOrder->agent_order_reference}."
                : 'Submitted, not emailed: send it to the supplier from the purchase order page.',
        ]);
    }

    /**
     * Only what the supplier still sells, as the Add Product picker offers it. Its own supplier code
     * wins over a SKO code, and a code that still means several products is refused rather than
     * guessed.
     *
     * @return Collection<int, OrgSupplierProduct>
     */
    private function orgSupplierProducts(OrgSupplier $orgSupplier, string $code): Collection
    {
        $code      = strtolower($code);
        $available = fn () => OrgSupplierProduct::where('org_supplier_id', $orgSupplier->id)
            ->where('state', OrgSupplierProductStateEnum::ACTIVE)
            ->where('is_available', true)
            ->whereHas('supplierProduct', fn ($query) => $query->where('is_available', true))
            ->with('supplierProduct');

        $bySupplierCode = $available()->whereHas('supplierProduct', fn ($query) => $query->whereRaw('lower(code) = ?', [$code]))->get();
        if ($bySupplierCode->isNotEmpty()) {
            return $bySupplierCode;
        }

        return $available()->whereExists(fn ($query) => $query->selectRaw('1')
            ->from('org_stock_has_org_supplier_products')
            ->join('org_stocks', 'org_stocks.id', 'org_stock_has_org_supplier_products.org_stock_id')
            ->whereColumn('org_stock_has_org_supplier_products.org_supplier_product_id', 'org_supplier_products.id')
            ->whereRaw('lower(org_stocks.code) = ?', [$code]))
            ->get();
    }

    /**
     * Whole cartons and never below the supplier's carton minimum, as the suggested shopping lists
     * round them.
     *
     * @return array{org_supplier_product: OrgSupplierProduct, asked: float, units: int, cartons: int, units_per_carton: int, unit_cost: float}
     */
    private function line(OrgSupplierProduct $orgSupplierProduct, float $quantity): array
    {
        $supplierProduct = $orgSupplierProduct->supplierProduct;
        $cartonUnits     = max(1, (int) ($supplierProduct->units_per_carton ?: 1));
        $cartons         = max(max(1, (int) ($supplierProduct->minimum_carton_order ?: 1)), (int) ceil($quantity / $cartonUnits));

        return [
            'org_supplier_product' => $orgSupplierProduct,
            'asked'                => $quantity,
            'units'                => $cartons * $cartonUnits,
            'cartons'              => $cartons,
            'units_per_carton'     => $cartonUnits,
            'unit_cost'            => (float) $supplierProduct->cost,
        ];
    }

    /**
     * Placing submits the whole order being prepared, so the preview shows its lines and the total
     * that will be sent, not only what is added.
     *
     * @param  array<int, array{org_supplier_product: OrgSupplierProduct, asked: float, units: int, cartons: int, units_per_carton: int, unit_cost: float}>  $lines
     *
     * @return array<string, mixed>
     */
    private function preview(OrgSupplier $orgSupplier, array $lines, ?PurchaseOrder $beingPrepared): array
    {
        $organisation  = $orgSupplier->organisation;
        $newTotal      = round(collect($lines)->sum(fn (array $line) => $line['units'] * $line['unit_cost']), 2);
        $orgExchange   = $beingPrepared?->org_exchange ?? GetHistoricCurrencyExchange::run($orgSupplier->supplier->currency, $organisation->currency, now()->startOfDay());
        $preparedLines = $beingPrepared ? $beingPrepared->purchaseOrderTransactions()->with('supplierProduct')->get() : collect();
        $preparedTotal = round((float) $preparedLines->sum('org_net_amount'), 2);

        return [
            'supplier'        => $orgSupplier->supplier->code.' ('.$orgSupplier->supplier->name.')',
            'agent'           => $orgSupplier->orgAgent ? $orgSupplier->orgAgent->agent->organisation->name : null,
            'agent_order'     => $orgSupplier->orgAgent ? $this->agentOrder($orgSupplier, $beingPrepared) : null,
            'currency'        => $orgSupplier->supplier->currency->code,
            'being_prepared'  => $beingPrepared ? [
                'reference' => $beingPrepared->reference,
                'started_by' => $beingPrepared->buyer?->contact_name ?? $beingPrepared->buyer?->username,
                'note'      => 'Placing adds the new lines to this order and submits it whole, including these lines already on it',
                'lines'     => $preparedLines->map(fn ($transaction) => [
                    'code'  => $transaction->supplierProduct?->code,
                    'units' => (float) $transaction->quantity_ordered,
                    'cost'  => round((float) $transaction->net_amount, 2).' '.$transaction->supplierProduct?->currency?->code,
                ])->values()->all(),
                'total_in_organisation_currency' => $preparedTotal,
            ] : null,
            'new_lines_total' => $newTotal,
            'organisation_currency' => $organisation->currency->code,
            'order_total_in_organisation_currency' => round($newTotal * (float) $orgExchange + $preparedTotal, 2),
            'lines'           => collect($lines)->map(fn (array $line) => [
                'code'             => $line['org_supplier_product']->supplierProduct->code,
                'name'             => $line['org_supplier_product']->supplierProduct->name,
                'units_asked'      => $line['asked'],
                'units'            => $line['units'],
                'cartons'          => $line['cartons'],
                'units_per_carton' => $line['units_per_carton'],
                'units_per_pack'   => $line['org_supplier_product']->supplierProduct->units_per_pack,
                'unit_cost'        => $line['unit_cost'],
                'cost'             => round($line['units'] * $line['unit_cost'], 2),
            ])->values()->all(),
        ];
    }

    /**
     * Placing submits only this supplier's order; the other suppliers' drafts of the same agent order
     * stay to be submitted together from the agent's page.
     *
     * @return array{reference: string, other_suppliers_being_prepared: array<int, string>, note: string}
     */
    private function agentOrder(OrgSupplier $orgSupplier, ?PurchaseOrder $beingPrepared): array
    {
        $reference = $beingPrepared?->agent_order_reference ?? ResolveAgentOrderReference::make()->previewAgentOrderReference($orgSupplier->orgAgent);

        return [
            'reference'                      => $reference,
            'other_suppliers_being_prepared' => PurchaseOrder::inAgentOrder($orgSupplier->organisation_id, $orgSupplier->orgAgent->agent_id, $reference)
                ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
                ->where('parent_id', '!=', $orgSupplier->id)
                ->orderBy('parent_code')
                ->pluck('parent_code')
                ->all(),
            'note'                           => 'Placing submits only this supplier\'s order. Send it to the agent from Procurement > Agents > the agent > Agent orders; other suppliers\' drafts there are submitted separately.',
        ];
    }

    /**
     * @param  array<int, array{org_supplier_product: OrgSupplierProduct, units: int}>  $lines
     */
    private function place(OrgSupplier $orgSupplier, array $lines, int $buyerId): PurchaseOrder
    {
        return DB::transaction(function () use ($orgSupplier, $lines, $buyerId) {
            OrgSupplier::whereKey($orgSupplier->id)->lockForUpdate()->first();

            $purchaseOrder = $orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest()->first()
                ?? StorePurchaseOrder::make()->action($orgSupplier, ['buyer_id' => $buyerId]);

            $storeTransaction          = StorePurchaseOrderTransaction::make();
            $storeTransaction->batched = true;
            foreach ($lines as $line) {
                $storeTransaction->addOrgSupplierProduct($purchaseOrder, $line['org_supplier_product'], ['quantity_ordered' => $line['units']]);
            }

            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::run($purchaseOrder);

            return UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Slug or code of the buying organisation')->required(),
            'supplier'     => $schema->string()->description('Code of the supplier (not the agent)')->required(),
            'lines'        => $schema->array()->items($schema->object([
                'code'     => $schema->string()->description('Supplier product code or SKO code')->required(),
                'quantity' => $schema->number()->description('Units wanted; rounded up to whole cartons')->required(),
            ]))->description('What to order')->required(),
            'place'        => $schema->boolean()->description('Users enrolled to place orders only: add the lines to the order being prepared for this supplier, or a new one, and submit it'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required to place'),
        ];
    }
}
