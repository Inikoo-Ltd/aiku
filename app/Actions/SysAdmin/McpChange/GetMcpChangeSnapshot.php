<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpChange;

use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The state a change type touches, read the same way before a change, after it and before a
 * revert: a revert only goes ahead while the current snapshot still equals the stored after.
 */
class GetMcpChangeSnapshot
{
    use AsObject;

    public const array PRODUCTION_TABLES = [
        'artefact'         => 'artefacts',
        'raw_material'     => 'raw_materials',
        'manufacture_task' => 'manufacture_tasks',
    ];

    public const array PRODUCTION_RECORD_FIELDS = [
        'artefact'         => ['id', 'code', 'name', 'state', 'recommended_batch_size', 'shelf_life_days', 'artefact_family_id', 'artefact_department_id', 'org_stock_id', 'trade_unit_id'],
        'raw_material'     => ['id', 'code', 'description', 'type', 'state', 'unit', 'unit_cost', 'org_stock_id', 'trade_unit_id'],
        'manufacture_task' => ['id', 'code', 'name', 'description', 'status', 'is_piece_rate'],
    ];

    public function handle(McpChangeTypeEnum $type, array $target): array
    {
        return match ($type) {
            McpChangeTypeEnum::RELATED_PRODUCTS => $this->relatedProducts($target),
            McpChangeTypeEnum::ORG_STOCK_STATE  => $this->orgStockStates($target),
            McpChangeTypeEnum::PARTNER_SHOPPING_LIST => $this->partnerShoppingList($target),
            McpChangeTypeEnum::PRODUCTION_RECORD => $this->productionRecord($target),
            McpChangeTypeEnum::PRODUCTION_RECIPE => $this->productionRecipes($target),
            McpChangeTypeEnum::PLACED_ORDER => $this->placedOrder($target),
        };
    }

    public function describe(McpChangeTypeEnum $type, array $target, array $snapshot): string
    {
        if ($type === McpChangeTypeEnum::ORG_STOCK_STATE) {
            return collect($snapshot['org_stocks'])
                ->map(fn (array $orgStock) => $orgStock['code'].': '.$orgStock['state'].($orgStock['scheduled'] ? ' (scheduled '.$orgStock['scheduled']['to_state'].')' : ''))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::PARTNER_SHOPPING_LIST) {
            $codes = DB::table('stocks')->whereIn('id', $target['stock_ids'])->pluck('code', 'id');

            return collect($target['stock_ids'])
                ->map(fn ($stockId) => ($codes[$stockId] ?? '#'.$stockId).': '.($snapshot['lines'][$stockId]['quantity'] ?? 'not on list'))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::PLACED_ORDER) {
            return trans_choice(':count line in the basket|:count lines in the basket', $snapshot['basket_lines'])
                .($snapshot['sent_lines'] ? ', sent: '.collect($snapshot['sent_lines'])->implode(', ') : '')
                .collect($snapshot['purchase_orders'])->map(fn (array $purchaseOrder) => ', '.$purchaseOrder['reference'].': '.$purchaseOrder['state'].' '.$purchaseOrder['lines'].' lines')->implode('');
        }

        if ($type === McpChangeTypeEnum::PRODUCTION_RECORD) {
            return collect($snapshot)->map(fn ($value, $field) => $field.'='.($value ?? '-'))->implode(', ') ?: 'not there';
        }

        if ($type === McpChangeTypeEnum::PRODUCTION_RECIPE) {
            $steps         = collect($snapshot['artefacts'])->flatten(1);
            $taskCodes     = DB::table('manufacture_tasks')->whereIn('id', $steps->pluck('manufacture_task_id'))->pluck('code', 'id');
            $materialCodes = DB::table('raw_materials')->whereIn('id', $steps->pluck('raw_materials')->flatten(1)->pluck('raw_material_id'))->pluck('code', 'id');
            $artefactCodes = DB::table('artefacts')->whereIn('id', $target['artefact_ids'])->pluck('code', 'id');

            return collect($snapshot['artefacts'])
                ->map(fn (array $recipe, $artefactId) => ($artefactCodes[$artefactId] ?? '#'.$artefactId).': '.(collect($recipe)
                    ->map(fn (array $step) => $step['position'].' '.($taskCodes[$step['manufacture_task_id']] ?? '#'.$step['manufacture_task_id'])
                        .' x'.(float) $step['units_per_artefact']
                        .($step['standard_rate'] !== null ? ' @'.(float) $step['standard_rate'].'/h' : '')
                        .collect($step['raw_materials'])->map(fn (array $rawMaterial) => ' +'.($materialCodes[$rawMaterial['raw_material_id']] ?? '#'.$rawMaterial['raw_material_id']).' '.(float) $rawMaterial['quantity_per_unit'])->implode(''))
                    ->implode('; ') ?: 'no steps'))
                ->implode(' | ');
        }

        $codes = DB::table($target['level'] === 'master' ? 'master_assets' : 'products')
            ->whereIn('id', $snapshot['ids'])
            ->pluck('code', 'id');

        return collect($snapshot['ids'])->map(fn ($id) => $codes[$id] ?? '#'.$id)->implode(', ') ?: '-';
    }

    private function relatedProducts(array $target): array
    {
        $query = $target['level'] === 'master'
            ? DB::table('master_product_category_has_related_assets')->where('master_product_category_id', $target['id'])->select('master_asset_id as id')
            : DB::table('product_category_has_related_products')->where('product_category_id', $target['id'])->select('product_id as id');

        return ['ids' => $query->orderBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }

    /**
     * The basket drafts and the purchase orders still being prepared or just sent, so the log shows
     * what an order the assistant placed took from the basket and where it went.
     */
    private function placedOrder(array $target): array
    {
        return [
            'basket_lines'    => PartnerShoppingListItem::where('org_partner_id', $target['org_partner_id'])
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->count(),
            'sent_lines'      => PartnerShoppingListItem::where('org_partner_id', $target['org_partner_id'])
                ->whereIn('stock_id', $target['stock_ids'] ?? [])
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->orderBy('id')
                ->pluck('quantity', 'id')
                ->map(fn ($quantity) => (float) $quantity)
                ->all(),
            'purchase_orders' => DB::table('purchase_orders')
                ->where('parent_type', 'OrgPartner')
                ->where('parent_id', $target['org_partner_id'])
                ->whereIn('state', [PurchaseOrderStateEnum::IN_PROCESS->value, PurchaseOrderStateEnum::SUBMITTED->value])
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(5)
                ->select(['id', 'reference', 'state'])
                ->selectRaw('(select count(*) from purchase_order_transactions where purchase_order_id = purchase_orders.id) as number_lines')
                ->get()
                ->mapWithKeys(fn ($purchaseOrder) => [$purchaseOrder->id => [
                    'reference' => $purchaseOrder->reference,
                    'state'     => $purchaseOrder->state,
                    'lines'     => (int) $purchaseOrder->number_lines,
                ]])
                ->all(),
        ];
    }

    private function partnerShoppingList(array $target): array
    {
        return [
            'lines' => PartnerShoppingListItem::where('org_partner_id', $target['org_partner_id'])
                ->whereIn('stock_id', $target['stock_ids'])
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->whereNull('job_order_id')
                ->whereNull('pre_picked_at')
                ->orderBy('stock_id')
                ->get()
                ->mapWithKeys(fn (PartnerShoppingListItem $item) => [
                    $item->stock_id => [
                        'id'       => $item->id,
                        'quantity' => (float) $item->quantity,
                        'notes'    => $item->notes,
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * A record is found by id once it exists, and by code while it is being created.
     */
    private function productionRecord(array $target): array
    {
        $record = DB::table(self::PRODUCTION_TABLES[$target['kind']])
            ->where('organisation_id', $target['organisation_id'])
            ->whereNull('deleted_at')
            ->when(
                $target['id'] ?? null,
                fn ($query, $id) => $query->where('id', $id),
                fn ($query) => $query->whereRaw('lower(code) = ?', [strtolower($target['code'])])
            )
            ->first(self::PRODUCTION_RECORD_FIELDS[$target['kind']]);

        return $record ? (array) $record : [];
    }

    private function productionRecipes(array $target): array
    {
        $steps = DB::table('artefacts_manufacture_tasks')
            ->whereIn('artefact_id', $target['artefact_ids'])
            ->orderBy('position')
            ->orderBy('manufacture_task_id')
            ->get(['id', 'artefact_id', 'manufacture_task_id', 'position', 'units_per_artefact', 'standard_rate']);

        $rawMaterials = DB::table('recipe_step_raw_materials')
            ->whereIn('artefact_manufacture_task_id', $steps->pluck('id'))
            ->orderBy('raw_material_id')
            ->get(['artefact_manufacture_task_id', 'raw_material_id', 'quantity_per_unit'])
            ->groupBy('artefact_manufacture_task_id');

        return [
            'artefacts' => collect($target['artefact_ids'])
                ->mapWithKeys(fn ($artefactId) => [
                    $artefactId => $steps->where('artefact_id', $artefactId)->map(fn ($step) => [
                        'manufacture_task_id' => $step->manufacture_task_id,
                        'position'            => $step->position,
                        'units_per_artefact'  => $step->units_per_artefact,
                        'standard_rate'       => $step->standard_rate,
                        'raw_materials'       => collect($rawMaterials->get($step->id, []))->map(fn ($rawMaterial) => [
                            'raw_material_id'   => $rawMaterial->raw_material_id,
                            'quantity_per_unit' => $rawMaterial->quantity_per_unit,
                        ])->values()->all(),
                    ])->values()->all(),
                ])
                ->all(),
        ];
    }

    private function orgStockStates(array $target): array
    {
        return [
            'org_stocks' => OrgStock::whereIn('id', $target['org_stock_ids'])
                ->with('organisation:id,code')
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (OrgStock $orgStock) => [
                    $orgStock->id => [
                        'code'      => $orgStock->code.' ('.$orgStock->organisation->code.')',
                        'state'     => $orgStock->state->value,
                        'scheduled' => Arr::get($orgStock->data, DiscontinueOrgStocks::SCHEDULED_KEY),
                    ],
                ])
                ->all(),
        ];
    }
}
