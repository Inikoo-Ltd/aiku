<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Production\JobOrderItem\GetOpenJobOrderItemsOffBatch;
use App\Actions\Production\Artefact\GetArtefactComplianceStatus;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Http\Resources\Production\ArtefactBatchesResource;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactShowcase
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $compliance = GetArtefactComplianceStatus::run($artefact);

        return [
            'code'              => $artefact->code,
            'name'              => $artefact->name,
            'state'             => $artefact->state,
            'state_label'       => $artefact->state->labels()[$artefact->state->value],
            'is_in_development' => $artefact->is_in_development,
            'artefact_department'   => $artefact->artefactDepartment ? ['slug' => $artefact->artefactDepartment->slug, 'name' => $artefact->artefactDepartment->name] : null,
            'tags'              => $artefact->tags->pluck('name'),
            'compliance_status' => $compliance['status'],
            'compliance_label'  => $compliance['label'],
            'recommended_batch_size' => $artefact->recommended_batch_size,
            'batch_pack'        => $this->getBatchPack($artefact),
            'jobs_off_batch'    => GetOpenJobOrderItemsOffBatch::run([$artefact->id])->map(fn (array $item) => array_merge($item, [
                'route' => [
                    'name'       => 'grp.org.productions.show.operations.job-orders.show',
                    'parameters' => [$artefact->organisation->slug, $artefact->production->slug, $item['job_order_slug']],
                ],
            ]))->all(),
            'recent_batches'    => ArtefactBatchesResource::collection(IndexArtefactBatches::make()->produced($artefact)->get())->resolve(),
            'todo_batches'      => ArtefactBatchesResource::collection(IndexArtefactBatches::make()->toDo($artefact)->get())->resolve(),
            'partners'          => $this->getPartners($artefact),
            'shelf_life_days'   => $artefact->shelf_life_days,
            'update_route'      => [
                'name'       => 'grp.models.production.artefacts.update',
                'parameters' => [$artefact->production_id, $artefact->id]
            ],
            'product_developments' => ProductDevelopment::whereIn('id', ProductDevelopmentItem::where('artefact_id', $artefact->id)->select('product_development_id'))
                ->orderByDesc('id')
                ->get(['id', 'slug', 'code', 'name'])
                ->map(fn (ProductDevelopment $productDevelopment) => [
                    'code'  => $productDevelopment->code,
                    'name'  => $productDevelopment->name,
                    'route' => ['name' => 'grp.product_developments.show', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'tab' => 'formulation']],
                ])->all(),
            'trade_unit' => $artefact->tradeUnit ? [
                'id'   => $artefact->tradeUnit->id,
                'code' => $artefact->tradeUnit->code,
                'name' => $artefact->tradeUnit->name,
            ] : null,
            'org_stock' => $artefact->orgStock ? [
                'id'                     => $artefact->orgStock->id,
                'code'                   => $artefact->orgStock->code,
                'quantity_in_locations'  => (float) $artefact->orgStock->quantity_in_locations,
                'route'                  => $this->getOrgStockRoute($artefact->orgStock),
            ] : null,
            'manufacture_tasks' => $artefact->manufactureTasks->map(fn ($task) => [
                'id'                 => $task->id,
                'code'               => $task->code,
                'name'               => $task->name,
                'position'           => $task->pivot->position,
                'units_per_artefact' => $task->pivot->units_per_artefact,
                'standard_rate'      => $task->pivot->standard_rate ?? $task->standard_rate,
            ]),
        ];
    }

    /**
     * Production at a glance, for the stock page of a manufacturing hub.
     */
    public function overview(Artefact $artefact): array
    {
        $toDo = IndexArtefactBatches::make()->toDo($artefact)->get();

        return [
            'code'                   => $artefact->code,
            'route'                  => [
                'name'       => 'grp.org.productions.show.crafts.artefacts.show',
                'parameters' => [$artefact->organisation->slug, $artefact->production->slug, $artefact->slug],
            ],
            'recommended_batch_size' => $artefact->recommended_batch_size,
            'open_jobs'              => $toDo->count(),
            'units_in_production'    => (float) $toDo->sum(fn ($batch) => $batch->quantity - $batch->quantity_received),
            'jobs_done'              => IndexArtefactBatches::make()->query($artefact)->where('job_order_items.quantity_received', '>', 0)->count(),
            'todo_batches'           => ArtefactBatchesResource::collection($toDo->take(5))->resolve(),
            'recent_batches'         => ArtefactBatchesResource::collection(IndexArtefactBatches::make()->produced($artefact, 5)->get())->resolve(),
            'partners'               => $this->getPartners($artefact),
        ];
    }

    /**
     * What each partner has open on its shopping list for this stock and what already waits in its goods-out bay, in SKOs.
     *
     * @return array<int, array{code: string, name: string, ordered: float, in_bay: float}>
     */
    private function getPartners(Artefact $artefact): array
    {
        $orgStock = $artefact->orgStock;

        if (!$orgStock) {
            return [];
        }

        return OrgPartner::where('organisation_id', $artefact->organisation_id)
            ->with('partner')
            ->get()
            ->map(fn (OrgPartner $orgPartner) => [
                'code'    => $orgPartner->partner->code,
                'name'    => $orgPartner->partner->name,
                'ordered' => (float) PartnerShoppingListItem::where('organisation_id', $orgPartner->partner_id)
                    ->where('partner_organisation_id', $artefact->organisation_id)
                    ->where('stock_id', $orgStock->stock_id)
                    ->where('state', ShoppingListItemStateEnum::OPEN)
                    ->sum('quantity'),
                'in_bay'  => (float) LocationOrgStock::where('org_stock_id', $orgStock->id)
                    ->whereIn('location_id', $orgPartner->bayIds())
                    ->sum('quantity'),
            ])
            ->filter(fn (array $partner) => $partner['ordered'] > 0 || $partner['in_bay'] > 0)
            ->values()
            ->all();
    }

    /**
     * The org stock lives in the warehouse section, only link to it for users allowed in there.
     *
     * @return array{name: string, parameters: array<string, int>}|null
     */
    private function getOrgStockRoute(OrgStock $orgStock): ?array
    {
        $user = request()->user();

        if (!$user) {
            return null;
        }

        $organisation  = $orgStock->organisation;
        $warehouseIds  = $organisation->warehouses()->pluck('id')->toArray();

        if (!$warehouseIds) {
            return null;
        }

        $permissions = [
            "inventory.{$organisation->id}.view",
            "accounting.{$organisation->id}.view",
        ];

        foreach ($warehouseIds as $warehouseId) {
            $permissions[] = "supervisor-stocks.$warehouseId.view";
            $permissions[] = "stocks.$warehouseId.view";
            $permissions[] = "fulfilment.view.$warehouseId.view";
        }

        if (!$user->authTo($permissions)) {
            return null;
        }

        return [
            'name'       => 'grp.majordomo.redirect_org_stock',
            'parameters' => ['orgStock' => $orgStock->id],
        ];
    }

    /**
     * The batch is made in units, the org stock is sold in packs, they do not have to agree.
     *
     * @return array{packed_in: int, batch_in_skos: float, suggested_batch_size: int|null}|null
     */
    private function getBatchPack(Artefact $artefact): ?array
    {
        $packedIn = $artefact->orgStock?->packed_in;

        if (!$artefact->recommended_batch_size || !$packedIn) {
            return null;
        }

        $remainder = $artefact->recommended_batch_size % $packedIn;

        return [
            'packed_in'            => $packedIn,
            'batch_in_skos'        => round($artefact->recommended_batch_size / $packedIn, 2),
            'suggested_batch_size' => $remainder
                ? max($packedIn, (int) round($artefact->recommended_batch_size / $packedIn) * $packedIn)
                : null,
        ];
    }
}
