<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpChange;

use App\Actions\Catalogue\ProductCategory\RelatedProducts\SyncProductCategoryRelatedProducts;
use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\UpdatePartnerShoppingListItem;
use App\Actions\Production\Artefact\DetachManufactureTaskFromArtefact;
use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Actions\Production\Artefact\SetArtefactState;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Actions\Production\ManufactureTask\UpdateManufactureTask;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProducts\SyncMasterProductCategoryRelatedMasterAssets;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterProductCategory;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\McpChange;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Puts back the state an AI change found, through the same actions the UI uses so caches and
 * cascades run. Refused when anything moved since the change: a later edit by a person is never
 * overwritten silently.
 */
class RevertMcpChange
{
    use AsAction;

    public function handle(McpChange $mcpChange, User $user): McpChange
    {
        if ($mcpChange->type === McpChangeTypeEnum::PLACED_ORDER) {
            throw ValidationException::withMessages(['message' => __('An order already sent cannot be taken back automatically. Cancel it with the partner or supplier.')]);
        }

        if ($mcpChange->reverted_at) {
            throw ValidationException::withMessages(['message' => __('This change was already reverted')]);
        }

        $target  = Arr::get($mcpChange->data, 'target', []);
        $current = GetMcpChangeSnapshot::run($mcpChange->type, $target);

        if ($current != $mcpChange->after) {
            throw ValidationException::withMessages(['message' => __('This was changed again after the AI change, so it cannot be reverted automatically. Look at it and fix it by hand.')]);
        }

        DB::transaction(function () use ($mcpChange, $target, $user) {
            match ($mcpChange->type) {
                McpChangeTypeEnum::RELATED_PRODUCTS => $this->revertRelatedProducts($target, $mcpChange->before),
                McpChangeTypeEnum::ORG_STOCK_STATE  => $this->revertOrgStockStates($mcpChange->before),
                McpChangeTypeEnum::PARTNER_SHOPPING_LIST => $this->revertPartnerShoppingList($mcpChange->before, $mcpChange->after),
                McpChangeTypeEnum::PRODUCTION_RECORD => $this->revertProductionRecord($target, $mcpChange->before),
                McpChangeTypeEnum::PRODUCTION_RECIPE => $this->revertProductionRecipes($mcpChange->before),
                McpChangeTypeEnum::PLACED_ORDER => null,
            };

            $mcpChange->update([
                'reverted_at'    => now(),
                'reverted_by_id' => $user->id,
            ]);
        });

        return $mcpChange;
    }

    private function revertRelatedProducts(array $target, array $before): void
    {
        if ($target['level'] === 'master') {
            SyncMasterProductCategoryRelatedMasterAssets::make()->action(MasterProductCategory::findOrFail($target['id']), ['master_asset_ids' => $before['ids']]);

            return;
        }

        SyncProductCategoryRelatedProducts::make()->action(ProductCategory::findOrFail($target['id']), ['product_ids' => $before['ids']]);
    }

    private function revertPartnerShoppingList(array $before, array $after): void
    {
        foreach ($after['lines'] as $stockId => $line) {
            $item     = PartnerShoppingListItem::findOrFail($line['id']);
            $previous = $before['lines'][$stockId] ?? null;

            if ($previous) {
                UpdatePartnerShoppingListItem::make()->action($item, ['quantity' => $previous['quantity'], 'notes' => $previous['notes'], 'break_batch' => true]);
            } else {
                DeletePartnerShoppingListItem::make()->action($item);
            }
        }
    }

    /**
     * A record the assistant created has job orders, recipes and stock hanging off it soon after,
     * so it is never removed automatically.
     */
    private function revertProductionRecord(array $target, array $before): void
    {
        if (!$before) {
            throw ValidationException::withMessages(['message' => __('This change created :code, which cannot be undone automatically. Set it as discontinued or inactive instead.', ['code' => $target['code']])]);
        }

        $fields = Arr::except($before, ['id']);

        if ($target['kind'] === 'artefact') {
            $artefact = Artefact::findOrFail($before['id']);
            if ($artefact->state->value !== $fields['state']) {
                SetArtefactState::make()->action($artefact, ArtefactStateEnum::from($fields['state']));
            }
            UpdateArtefact::make()->action($artefact, Arr::except($fields, ['state']));

            return;
        }

        match ($target['kind']) {
            'raw_material'     => UpdateRawMaterial::make()->action(RawMaterial::findOrFail($before['id']), $fields),
            'manufacture_task' => UpdateManufactureTask::make()->action(ManufactureTask::findOrFail($before['id']), $fields),
        };
    }

    private function revertProductionRecipes(array $before): void
    {
        foreach ($before['artefacts'] as $artefactId => $steps) {
            $artefact = Artefact::findOrFail($artefactId);

            if (!$steps) {
                $artefact->manufactureTasks->each(fn (ManufactureTask $manufactureTask) => DetachManufactureTaskFromArtefact::make()->action($artefact, $manufactureTask));

                continue;
            }

            SetArtefactsRecipe::make()->action($artefact->production, [
                'artefacts' => [$artefact->id],
                'steps'     => $steps,
            ]);
        }
    }

    private function revertOrgStockStates(array $before): void
    {
        foreach ($before['org_stocks'] as $orgStockId => $previous) {
            $orgStock = OrgStock::findOrFail($orgStockId);

            if ($orgStock->state->value !== $previous['state']) {
                $orgStock = UpdateOrgStock::make()->action($orgStock, ['state' => $previous['state']]);
            }

            $data = Arr::except($orgStock->data, [DiscontinueOrgStocks::SCHEDULED_KEY]);
            if ($previous['scheduled']) {
                $data[DiscontinueOrgStocks::SCHEDULED_KEY] = $previous['scheduled'];
            }
            $orgStock->update(['data' => $data]);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('mcpChange')->canBeRevertedBy($request->user());
    }

    public function asController(McpChange $mcpChange, ActionRequest $request): RedirectResponse
    {
        $this->handle($mcpChange, $request->user());

        return back();
    }
}
