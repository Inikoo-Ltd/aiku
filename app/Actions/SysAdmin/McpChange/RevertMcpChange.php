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
use App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProducts\SyncMasterProductCategoryRelatedMasterAssets;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\McpChange;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
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
        if ($mcpChange->reverted_at) {
            throw ValidationException::withMessages(['message' => __('This change was already reverted')]);
        }

        $target  = Arr::get($mcpChange->data, 'target', []);
        $current = GetMcpChangeSnapshot::run($mcpChange->type, $target);

        if ($current != $mcpChange->after) {
            throw ValidationException::withMessages(['message' => __('This was changed again after the AI change, so it cannot be reverted automatically. Look at it and fix it by hand.')]);
        }

        match ($mcpChange->type) {
            McpChangeTypeEnum::RELATED_PRODUCTS => $this->revertRelatedProducts($target, $mcpChange->before),
            McpChangeTypeEnum::ORG_STOCK_STATE  => $this->revertOrgStockStates($mcpChange->before),
        };

        $mcpChange->update([
            'reverted_at'    => now(),
            'reverted_by_id' => $user->id,
        ]);

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
