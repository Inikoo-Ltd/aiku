<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Goods\Packaging\SyncPackingDropdownComponents;
use App\Actions\SupplyChain\PackingSpec\StorePackingSpec;
use App\Enums\SupplyChain\PackingSpec\PackingSpecLevelEnum;
use App\Enums\SupplyChain\PackingSpec\PackingSpecPackedByEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Goods\PackagingComponent;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\PackingSpecLine;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierProductPackingSpec;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

/**
 * Packaging weighed at our warehouse goes on the inbound Packing Spec of the SKO's supplier product, the same record
 * agents declare and goods in weighs, so the EPR returns read it with the rest of the inbound packing. Each save is a
 * new spec version through StorePackingSpec: lines at the levels sent replace the live spec's lines at those levels,
 * the other levels (the agent's carton) are carried over, and the weighed lines are marked measured. Sending what the
 * live spec already holds changes nothing, so a daily re-run of the same sheet is harmless. A version whose every line
 * was weighed is marked materials_weighed, so goods in does not ask to weigh them again on its first delivery.
 */
#[Description('Records the inbound packaging of SKOs, weighed at our warehouse, on the Packing Spec of the SKO\'s supplier product (used by the EPR returns). Each item names a SKO code and its packaging lines: material (one of: bubble_wrap, air_pillow, epe_foam, eps_foam, shrink_film, stretch_film, pe_bag, opp_bag, zip_bag, tissue_paper, kraft_paper, newspaper, shredded_paper, honeycomb_paper, cardboard_sheet, pulp_tray, pack_box, master_carton, wood_wool, wooden_crate, textile_bag, strapping, plastic_tape, paper_tape, label_insert, silica_gel, other), weight_g of one piece, quantity (default 1) and level (per_pack = per SKO, the default; per_unit; per_carton). Map what was measured to the closest material: paper box = pack_box, plastic bag = pe_bag (opp_bag if clear and crisp), label or paper tag = label_insert; anything else is other with a description (rubber band). Manuals, instructions and leaflets are not packaging: leave them out and tell the user they go in Non-packaging materials on the trade unit\'s Edit page. The lines sent replace the live spec\'s lines at the same levels; other levels are kept. A SKO bought from several suppliers needs supplier (the supplier code). SKOs whose live spec already holds the same lines are skipped as unchanged. Only call after the user confirmed the SKOs and weights in their own words, passing their request text. Logged in the AI changes log and revertable.')]
class PackingSpecTool extends AikuOrgStockDiscontinueTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'              => ['required', 'string'],
            'items'                     => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sko'               => ['required', 'string'],
            'items.*.supplier'          => ['sometimes', 'nullable', 'string'],
            'items.*.lines'             => ['required', 'array', 'min:1', 'max:30'],
            'items.*.lines.*.material'  => ['required', Rule::in(array_keys(SyncPackingDropdownComponents::ITEMS))],
            'items.*.lines.*.weight_g'  => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.lines.*.quantity'  => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'items.*.lines.*.level'     => ['sometimes', 'nullable', Rule::enum(PackingSpecLevelEnum::class)],
            'items.*.lines.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'request_text'              => ['required', 'string', 'max:4000'],
        ]);

        if (!$request->user()?->can_use_mcp_procurement) {
            return Response::error('Recording packing specs is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $organisation = $this->organisationByCode($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        if (!$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_EDIT->value, $organisation))) {
            return Response::error('Recording packing specs needs procurement edit permission in '.$organisation->code.'. Do not retry.');
        }

        $items = collect($request->get('items'));

        [$orgStocks, $missing] = $this->orgStocksByCodes($organisation, $items->pluck('sko')->all());
        if ($missing) {
            return Response::error('Unknown SKO codes in '.$organisation->code.': '.implode(', ', $missing).'. Nothing was changed.');
        }
        $orgStocks = $orgStocks->keyBy('code');

        $components = PackagingComponent::where('group_id', $organisation->group_id)->where('is_dropdown_item', true)->get()->keyBy('dropdown_key');

        $resolved = $items->map(fn (array $item) => $this->resolve($orgStocks[trim($item['sko'])], $item, $components));
        $toSave   = $resolved->filter(fn (array $row) => isset($row['supplier_product']));
        $results  = $resolved->map(fn (array $row) => Arr::except($row, ['supplier_product', 'lines']))->all();

        if ($toSave->isNotEmpty()) {
            $supplierProductIds = $toSave->map(fn (array $row) => $row['supplier_product']->id)->unique()->values()->all();

            DB::transaction(function () use ($request, $organisation, $toSave, $supplierProductIds, &$results) {
                $this->recordChange(
                    $request,
                    McpChangeTypeEnum::PACKING_SPECS,
                    'Packing specs recorded in '.$organisation->code.': '.$toSave->pluck('sko')->implode(', '),
                    ['supplier_product_ids' => $supplierProductIds],
                    function () use ($request, $toSave, &$results) {
                        foreach ($toSave as $index => $row) {
                            $spec              = $this->save($row['supplier_product'], $row['lines'], $request->user());
                            $results[$index]  += ['result' => 'recorded', 'version' => $spec->version];
                        }
                    }
                );
            });
        }

        return Response::json([
            'organisation'  => $organisation->code,
            'items'         => $results,
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @param Collection<string, PackagingComponent> $components the group's packing materials by dropdown key
     *
     * @return array{sko: string, supplier_product?: SupplierProduct, lines?: list<array<string, mixed>>, supplier?: string, result?: string, reason?: string}
     */
    private function resolve(OrgStock $orgStock, array $item, Collection $components): array
    {
        $result   = ['sko' => $orgStock->code];
        $supplier = strtolower(trim((string) Arr::get($item, 'supplier')));

        $supplierProducts = SupplierProduct::query()
            ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
            ->whereIn('supplier_products.id', DB::table('org_stock_has_org_supplier_products')
                ->join('org_supplier_products', 'org_supplier_products.id', '=', 'org_stock_has_org_supplier_products.org_supplier_product_id')
                ->where('org_stock_has_org_supplier_products.org_stock_id', $orgStock->id)
                ->select('org_supplier_products.supplier_product_id'))
            ->when($supplier !== '', fn ($query) => $query->whereRaw('lower(suppliers.code) = ?', [$supplier]))
            ->get(['supplier_products.*', 'suppliers.code as supplier_code']);

        if ($supplierProducts->isEmpty()) {
            return $result + ['result' => 'skipped', 'reason' => $supplier !== '' ? 'The SKO is not supplied by '.$item['supplier'] : 'The SKO has no supplier product to hold its packing spec'];
        }

        if ($supplierProducts->count() > 1) {
            return $result + ['result' => 'skipped', 'reason' => 'Bought from several suppliers; pass supplier with one of: '.$supplierProducts->pluck('supplier_code')->unique()->implode(', ')];
        }

        $lines = [];
        foreach ($item['lines'] as $line) {
            $component = $components->get($line['material']);
            if (!$component) {
                return $result + ['result' => 'skipped', 'reason' => 'Packing material '.$line['material'].' is not set up; run packaging:sync-packing-dropdown'];
            }
            if ($component->needs_review && !Arr::get($line, 'description')) {
                return $result + ['result' => 'skipped', 'reason' => 'A line with material other needs a description'];
            }
            $lines[] = [
                'packaging_component_id' => $component->id,
                'level'                  => Arr::get($line, 'level') ?: PackingSpecLevelEnum::PER_PACK->value,
                'quantity'               => (float) (Arr::get($line, 'quantity') ?: 1),
                'weight_g_each'          => round((float) $line['weight_g'], 3),
                'description'            => Arr::get($line, 'description') ?: null,
            ];
        }

        /** @var SupplierProduct $supplierProduct */
        $supplierProduct = $supplierProducts->first();
        $result         += ['supplier' => $supplierProduct->supplier_code];
        $live            = $supplierProduct->livePackingSpec;

        if ($live) {
            $levels  = array_unique(array_column($lines, 'level'));
            $current = $live->lines()->orderBy('position')->get()
                ->filter(fn (PackingSpecLine $line) => in_array($line->level->value, $levels, true))
                ->map(fn (PackingSpecLine $line) => $this->comparable($line->packaging_component_id, $line->level->value, (float) $line->quantity, (float) $line->weight_g_each, $line->description));
            $sent = collect($lines)->map(fn (array $line) => $this->comparable($line['packaging_component_id'], $line['level'], $line['quantity'], $line['weight_g_each'], $line['description']));

            if ($current->sort()->values()->all() === $sent->sort()->values()->all()) {
                return $result + ['result' => 'unchanged', 'version' => $live->version];
            }
        }

        return $result + ['supplier_product' => $supplierProduct, 'lines' => $lines];
    }

    private function comparable(int $componentId, string $level, float $quantity, float $weight, ?string $description): string
    {
        return implode('|', [$componentId, $level, round($quantity, 3), round($weight, 3), (string) $description]);
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function save(SupplierProduct $supplierProduct, array $lines, User $user): SupplierProductPackingSpec
    {
        $supplierProduct = SupplierProduct::findOrFail($supplierProduct->id);
        $live            = $supplierProduct->livePackingSpec;
        $levels          = array_unique(array_column($lines, 'level'));

        $kept = $live
            ? $live->lines()->orderBy('position')->get()
                ->reject(fn (PackingSpecLine $line) => in_array($line->level->value, $levels, true))
                ->map(fn (PackingSpecLine $line) => [
                    'packaging_component_id' => $line->packaging_component_id,
                    'level'                  => $line->level->value,
                    'role'                   => $line->role?->value,
                    'quantity'               => (float) $line->quantity,
                    'weight_g_each'          => $line->weight_g_each === null ? null : (float) $line->weight_g_each,
                    'fate_at_our_warehouse'  => $line->fate_at_our_warehouse?->value,
                    'description'            => $line->description,
                    'weight_is_measured'     => $line->weight_is_measured,
                ])->values()->all()
            : [];

        $store = StorePackingSpec::make();
        $spec  = $store->handle($supplierProduct, [
            'packed_by'                      => $live?->packed_by?->value ?? ($supplierProduct->agent_id ? PackingSpecPackedByEnum::AGENT->value : PackingSpecPackedByEnum::SUPPLIER->value),
            'carton_length_mm'               => $live?->carton_length_mm,
            'carton_width_mm'                => $live?->carton_width_mm,
            'carton_height_mm'               => $live?->carton_height_mm,
            'declared_carton_gross_weight_g' => $live?->declared_carton_gross_weight_g,
            'lines'                          => [...$kept, ...$lines],
        ], $user, $live ? $store->photoOf($live) : null);

        $measured = array_keys(array_filter([...array_column($kept, 'weight_is_measured'), ...array_fill(0, count($lines), true)]));
        $spec->lines()->whereIn('position', $measured)->update(['weight_is_measured' => true]);

        if (count($measured) === count($kept) + count($lines)) {
            $spec->update(['materials_weighed' => true]);
        }

        return $spec;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation code or slug the SKO codes belong to')->required(),
            'items'        => $schema->array()->items($schema->object([
                'sko'      => $schema->string()->description('SKO code')->required(),
                'supplier' => $schema->string()->description('Supplier code, only when the SKO is bought from several suppliers'),
                'lines'    => $schema->array()->items($schema->object([
                    'material'    => $schema->string()->description('Packing material key, see the tool description')->required(),
                    'weight_g'    => $schema->number()->description('Weight of one piece in grams')->required(),
                    'quantity'    => $schema->number()->description('How many pieces at this level; default 1'),
                    'level'       => $schema->string()->enum(PackingSpecLevelEnum::values())->description('per_pack (per SKO, default), per_unit or per_carton'),
                    'description' => $schema->string()->description('What it is, e.g. "rubber band", "paper tag"; required for other'),
                ]))->description('The packaging measured on this SKO')->required(),
            ]))->description('Up to 100 SKOs')->required(),
            'request_text' => $schema->string()->description('The user\'s request, verbatim, kept in the change log')->required(),
        ];
    }
}
