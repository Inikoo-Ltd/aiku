<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\StoreSupplierProductForOrgStock;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

/**
 * Gated like the other procurement write tools (can_use_mcp_procurement plus procurement edit in the organisation).
 * Each SKO goes through the same actions as the SKO's Supplier products tab: an existing supplier product of that
 * supplier is attached, otherwise one is created from the SKO (trade units, packing) and attached.
 */
#[Description('Attaches SKOs (organisation stock) to suppliers so they can be ordered from them, creating the supplier product when the supplier does not have it yet. Each item names a SKO code and a supplier code (when several suppliers share it, the result lists their slugs: pass the right one as supplier_slug), so one call can attach SKOs to several suppliers. A supplier product of that supplier already linked to the SKO\'s stock, or with the same code, is reused; otherwise it is created from the SKO with the item\'s cost (per SKO, in the supplier\'s currency) and units_per_carton, which are then required (ask the user, never guess them). Discontinued SKOs, suppliers not set up in the organisation and SKOs already linked to that supplier are skipped with the reason. Only call after the user confirmed the SKOs, suppliers, costs and carton sizes in their own words, passing their request text. Logged in the AI changes log and revertable.')]
class SupplierProductLinkTool extends AikuOrgStockDiscontinueTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'             => ['required', 'string'],
            'items'                    => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sko'              => ['required', 'string'],
            'items.*.supplier'         => ['required', 'string'],
            'items.*.supplier_slug'    => ['sometimes', 'nullable', 'string'],
            'items.*.cost'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.units_per_carton' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.code'             => ['sometimes', 'nullable', 'string', 'max:64'],
            'items.*.name'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'request_text'             => ['required', 'string', 'max:4000'],
        ]);

        if (!$request->user()?->can_use_mcp_procurement) {
            return Response::error('Attaching SKOs to suppliers is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $organisation = $this->organisationByCode($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        if (!$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_EDIT->value, $organisation))) {
            return Response::error('Attaching SKOs to suppliers needs procurement edit permission in '.$organisation->code.'. Do not retry.');
        }

        $items = collect($request->get('items'));

        [$orgStocks, $missing] = $this->orgStocksByCodes($organisation, $items->pluck('sko')->all());
        if ($missing) {
            return Response::error('Unknown SKO codes in '.$organisation->code.': '.implode(', ', $missing).'. Nothing was changed.');
        }
        $orgStocks = $orgStocks->keyBy('code');

        $supplierKeys = $items->pluck('supplier')->merge($items->pluck('supplier_slug')->filter())->map(fn ($supplier) => strtolower(trim($supplier)))->unique()->values()->all();
        $orgSuppliers = OrgSupplier::where('organisation_id', $organisation->id)
            ->join('suppliers', 'suppliers.id', '=', 'org_suppliers.supplier_id')
            ->where(fn ($query) => $query->whereIn(DB::raw('lower(suppliers.code)'), $supplierKeys)->orWhereIn(DB::raw('lower(suppliers.slug)'), $supplierKeys))
            ->get(['org_suppliers.*', 'suppliers.code as supplier_code', 'suppliers.slug as supplier_slug']);

        $results = [];
        $changes = ['created_supplier_product_ids' => [], 'created_stock_supplier_links' => [], 'previous_packing' => []];

        DB::transaction(function () use ($request, $organisation, $items, $orgStocks, $orgSuppliers, &$results, &$changes) {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::SUPPLIER_PRODUCT_LINKS,
                'SKOs attached to suppliers in '.$organisation->code.': '.$items->map(fn (array $item) => $item['sko'].' → '.$item['supplier'])->implode(', '),
                ['org_stock_ids' => $orgStocks->pluck('id')->values()->all()],
                function () use ($items, $orgStocks, $orgSuppliers, &$results, &$changes) {
                    foreach ($items as $item) {
                        $slug    = strtolower(trim((string) Arr::get($item, 'supplier_slug')));
                        $key     = strtolower(trim($item['supplier']));
                        $matches = $slug !== ''
                            ? $orgSuppliers->filter(fn (OrgSupplier $orgSupplier) => strtolower($orgSupplier->supplier_slug) === $slug)->values()
                            : $orgSuppliers->filter(fn (OrgSupplier $orgSupplier) => in_array($key, [strtolower($orgSupplier->supplier_code), strtolower($orgSupplier->supplier_slug)], true))->values();

                        $results[] = $this->link($orgStocks[trim($item['sko'])], $matches, $item, $changes);
                    }
                }
            );

            if ($this->mcpChange && array_filter($changes)) {
                $this->mcpChange->update(['data' => array_merge($this->mcpChange->data, $changes)]);
            }
        });

        return Response::json([
            'organisation'  => $organisation->code,
            'items'         => $results,
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @param Collection<int, OrgSupplier> $orgSuppliers the organisation's suppliers matching the item's supplier code or slug
     * @param array{created_supplier_product_ids: list<int>, created_stock_supplier_links: list<int>, previous_packing: array<int, array{units_per_pack: ?int, units_per_carton: ?int}>} $changes what revert needs to undo exactly
     *
     * @return array{sko: string, supplier: string, result: string, supplier_product?: string, reason?: string}
     */
    private function link(OrgStock $orgStock, Collection $orgSuppliers, array $item, array &$changes): array
    {
        $result = ['sko' => $orgStock->code, 'supplier' => $item['supplier']];

        if ($orgSuppliers->isEmpty()) {
            return $result + ['result' => 'skipped', 'reason' => 'This supplier is not set up in the organisation'];
        }

        if ($orgSuppliers->count() > 1) {
            return $result + ['result' => 'skipped', 'reason' => 'Several suppliers match; pass supplier_slug with one of: '.$orgSuppliers->pluck('supplier_slug')->implode(', ')];
        }

        /** @var OrgSupplier $orgSupplier */
        $orgSupplier = $orgSuppliers->first();

        if ($orgStock->state === OrgStockStateEnum::DISCONTINUED) {
            return $result + ['result' => 'skipped', 'reason' => 'The SKO is discontinued in this organisation'];
        }

        $linked = OrgSupplierProduct::where('org_supplier_id', $orgSupplier->id)
            ->whereIn('id', DB::table('org_stock_has_org_supplier_products')->where('org_stock_id', $orgStock->id)->select('org_supplier_product_id'))
            ->first();
        if ($linked) {
            return $result + ['result' => 'skipped', 'reason' => 'Already linked to this supplier', 'supplier_product' => $linked->supplierProduct->code];
        }

        $code = trim(Arr::get($item, 'code') ?: $orgStock->code);

        $supplierProduct = SupplierProduct::where('supplier_id', $orgSupplier->supplier_id)
            ->whereIn('id', DB::table('stock_has_supplier_products')->where('stock_id', $orgStock->stock_id)->select('supplier_product_id'))
            ->first();

        if (!$supplierProduct) {
            $supplierProduct = SupplierProduct::withTrashed()->where('supplier_id', $orgSupplier->supplier_id)->whereRaw('lower(code) = ?', [strtolower($code)])->first();

            if ($supplierProduct?->trashed()) {
                return $result + ['result' => 'skipped', 'reason' => 'The supplier has a deleted product with code '.$supplierProduct->code.'; pass a different code'];
            }

            if ($supplierProduct && DB::table('stock_has_supplier_products')->where('supplier_product_id', $supplierProduct->id)->exists()) {
                return $result + ['result' => 'skipped', 'reason' => 'The supplier already has '.$supplierProduct->code.' for another SKO; pass a different code'];
            }
        }

        if ($supplierProduct) {
            $orgSupplierProduct = OrgSupplierProduct::where('organisation_id', $orgStock->organisation_id)->where('supplier_product_id', $supplierProduct->id)->first();
            if (!$orgSupplierProduct) {
                return $result + ['result' => 'skipped', 'reason' => 'Supplier product '.$supplierProduct->code.' is not set up in this organisation'];
            }

            $packing = $supplierProduct->only(['units_per_pack', 'units_per_carton']);
            $this->attach($orgStock, $orgSupplierProduct, $changes, $packing);

            return $result + ['result' => 'attached', 'supplier_product' => $supplierProduct->code];
        }

        if (Arr::get($item, 'cost') === null || !Arr::get($item, 'units_per_carton')) {
            return $result + ['result' => 'skipped', 'reason' => 'The supplier has no product for this SKO yet: cost and units_per_carton are needed to create it'];
        }

        try {
            $orgSupplierProduct = StoreSupplierProductForOrgStock::make()->action($orgStock, [
                'org_supplier_id'  => $orgSupplier->id,
                'code'             => $code,
                'name'             => Arr::get($item, 'name') ?: $orgStock->name,
                'cost'             => $item['cost'],
                'units_per_pack'   => $orgStock->packed_in ?: 1,
                'units_per_carton' => $item['units_per_carton'],
            ]);
        } catch (ValidationException $exception) {
            return $result + ['result' => 'skipped', 'reason' => implode(' ', Arr::flatten($exception->errors()))];
        }

        $changes['created_supplier_product_ids'][] = $orgSupplierProduct->supplier_product_id;
        $changes['created_stock_supplier_links'][] = StockHasSupplierProduct::where('stock_id', $orgStock->stock_id)->where('supplier_product_id', $orgSupplierProduct->supplier_product_id)->value('id');

        return $result + ['result' => 'created', 'supplier_product' => $code];
    }

    /**
     * A group stock link made here also re-syncs the supplier product's packing from the SKO, so its old packing is
     * kept for revert.
     */
    private function attach(OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct, array &$changes, array $packing): void
    {
        $hadStockLink = StockHasSupplierProduct::where('stock_id', $orgStock->stock_id)->where('supplier_product_id', $orgSupplierProduct->supplier_product_id)->exists();

        $link = AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $orgSupplierProduct);

        if (!$hadStockLink) {
            $changes['created_stock_supplier_links'][]                                  = $link->stock_has_supplier_product_id;
            $changes['previous_packing'][$orgSupplierProduct->supplier_product_id] = $packing;
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation code or slug the SKO codes belong to')->required(),
            'items'        => $schema->array()->items($schema->object([
                'sko'              => $schema->string()->description('SKO code')->required(),
                'supplier'         => $schema->string()->description('Supplier code')->required(),
                'supplier_slug'    => $schema->string()->description('Supplier slug, only when several suppliers share the code'),
                'cost'             => $schema->number()->description('Cost of one SKO in the supplier\'s currency; needed only when the supplier product has to be created'),
                'units_per_carton' => $schema->integer()->description('Units in one supplier carton; needed only when the supplier product has to be created'),
                'code'             => $schema->string()->description('Supplier product code, when it differs from the SKO code'),
                'name'             => $schema->string()->description('Supplier product name; defaults to the SKO name'),
            ]))->description('Up to 100 SKO to supplier pairs')->required(),
            'request_text' => $schema->string()->description('The user\'s request, verbatim, kept in the change log')->required(),
        ];
    }
}
