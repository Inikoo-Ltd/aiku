<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpChange;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\Production\WorkArea\DeleteWorkArea;
use App\Actions\Production\WorkArea\UpdateWorkArea;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Production\Production;
use App\Models\Production\WorkArea;
use App\Models\Goods\TradeUnit;
use App\Actions\Chat\ChatSession\ReopenChatSession;
use App\Actions\Chat\ChatSession\UnmarkChatSessionAsSpam;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatSession;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Catalogue\ProductCategory\RelatedProducts\SyncProductCategoryRelatedProducts;
use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\Procurement\PartnerShoppingListItem\UpdatePartnerShoppingListItem;
use App\Actions\Production\Artefact\DetachManufactureTaskFromArtefact;
use App\Actions\Production\Artefact\SetArtefactStepTarget;
use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Actions\Production\Artefact\SetArtefactState;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Actions\Production\ManufactureTask\UpdateManufactureTask;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProducts\SyncMasterProductCategoryRelatedMasterAssets;
use App\Actions\GoodsIn\StockDelivery\DeleteStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactStepTarget;
use App\Models\Production\ManufactureTask;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\McpChange;
use App\Models\SysAdmin\User;
use App\Actions\Web\Redirect\UpdateRedirect;
use App\Models\Web\Redirect;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Puts back the state an AI change found, through the same actions the UI uses so caches and
 * cascades run. Refused when anything moved since the change: a later edit by a person is never
 * overwritten silently. A chat the assistant put in spam comes back without touching the blocked
 * senders, since the assistant never blocks one.
 */
class RevertMcpChange
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(McpChange $mcpChange, User $user): McpChange
    {
        if ($mcpChange->type === McpChangeTypeEnum::PLACED_ORDER) {
            throw ValidationException::withMessages(['message' => __('An order already sent cannot be taken back automatically. Cancel it with the partner or supplier.')]);
        }

        if (!$mcpChange->type->canBeReverted()) {
            throw ValidationException::withMessages(['message' => __('What was done for a customer cannot be taken back automatically. Put it right in the chat or on the order.')]);
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
                McpChangeTypeEnum::SUPPLIER_PRODUCT_AVAILABILITY => $this->revertSupplierProductAvailability($mcpChange->before),
                McpChangeTypeEnum::PARTNER_SHOPPING_LIST => $this->revertPartnerShoppingList($mcpChange->before, $mcpChange->after),
                McpChangeTypeEnum::PRODUCTION_RECORD => $this->revertProductionRecord($target, $mcpChange->before),
                McpChangeTypeEnum::PRODUCTION_RECIPE => $this->revertProductionRecipes($mcpChange),
                McpChangeTypeEnum::PLACED_ORDER, McpChangeTypeEnum::CUSTOMER_SERVICE => null,
                McpChangeTypeEnum::CHAT_SPAM => UnmarkChatSessionAsSpam::make()->handle(ChatSession::findOrFail($target['chat_session_id']), $this->chatAgentProfileFor($user), unblockSender: false),
                McpChangeTypeEnum::CHAT_CLOSED => ReopenChatSession::make()->handle(ChatSession::findOrFail($target['chat_session_id']), $this->chatAgentProfileFor($user)),
                McpChangeTypeEnum::MASTER_PRICES => $this->revertMasterPrices($mcpChange->before),
                McpChangeTypeEnum::STOCK_DELIVERY_COSTS => $this->revertStockDeliveryCosts($target, $mcpChange->before),
                McpChangeTypeEnum::TRADE_UNIT_PROPERTIES => $this->revertTradeUnitProperties($target, $mcpChange->before, $mcpChange->after),
                McpChangeTypeEnum::WEBSITE_REDIRECTS => $this->revertWebsiteRedirects($mcpChange->before),
                McpChangeTypeEnum::STOCK_LOCATIONS => $this->revertStockLocations($target, $mcpChange->before, $mcpChange->after, $user),
                McpChangeTypeEnum::PRODUCTION_SETUP => $this->revertProductionSetup($target, $mcpChange->before),
                McpChangeTypeEnum::SUPPLIER_PRODUCT_LINKS => $this->revertSupplierProductLinks($mcpChange),
                McpChangeTypeEnum::ORG_STOCK_AUTO_ORDERING => $this->revertOrgStockAutoOrdering($mcpChange->before),
            };

            $mcpChange->update([
                'reverted_at'    => now(),
                'reverted_by_id' => $user->id,
            ]);
        });

        return $mcpChange;
    }

    /**
     * Only takes the SKOs the change added off their locations, and only while they hold nothing: the snapshot check
     * above already refused a location whose quantity moved since.
     */
    private function revertStockLocations(array $target, array $before, array $after, User $user): void
    {
        foreach ($target['pairs'] as $pair) {
            $key = $pair['org_stock_id'].'-'.$pair['location_id'];
            if (isset($before['pairs'][$key]) || !isset($after['pairs'][$key])) {
                continue;
            }

            $locationOrgStock = LocationOrgStock::where('org_stock_id', $pair['org_stock_id'])->where('location_id', $pair['location_id'])->firstOrFail();
            if ((float) $locationOrgStock->quantity != 0) {
                throw ValidationException::withMessages(['message' => __(':sko already has stock in :location, so it cannot be taken off automatically.', ['sko' => $pair['sko'], 'location' => $pair['location']])]);
            }

            DeleteLocationOrgStock::make()->action($locationOrgStock, $user);
        }
    }

    private function revertProductionSetup(array $target, array $before): void
    {
        $production = Production::findOrFail($target['production_id']);
        $production->update(['factory_location_id' => $before['factory_location_id']]);

        foreach (WorkArea::where('production_id', $production->id)->get() as $workArea) {
            if (!isset($before['areas'][$workArea->id])) {
                DeleteWorkArea::make()->action($workArea);
            }
        }

        foreach ($before['areas'] as $workAreaId => $area) {
            $workArea = WorkArea::where('production_id', $production->id)->find($workAreaId);
            if (!$workArea) {
                continue;
            }

            UpdateWorkArea::make()->action($workArea, ['name' => $area['name'], 'artefact_department_ids' => $area['department_ids']]);
            $current = $workArea->artisans()->pluck('employees.id')->all();
            if ($leaving = array_diff($current, $area['artisan_ids'])) {
                $workArea->artisans()->detach($leaving);
            }
            foreach (array_diff($area['artisan_ids'], $current) as $employeeId) {
                AttachArtisan::make()->handle($workArea, ['employee_id' => $employeeId]);
            }
        }
    }

    private function revertStockDeliveryCosts(array $target, array $before): void
    {
        $stockDelivery = StockDelivery::findOrFail($target['stock_delivery_id']);
        if ($stockDelivery->is_costed) {
            throw ValidationException::withMessages(['message' => __('This stock delivery is costed now, so its costs cannot be reverted automatically. An accounting manager changes it with Update costing.')]);
        }

        $costs = $before['costs'];

        $stockDelivery->costs()->where('from_service_invoices', false)->whereNotIn('id', array_keys($costs))->get()->each(function (StockDeliveryCost $cost) use ($stockDelivery) {
            if ($cost->type === StockDeliveryCostTypeEnum::EXTRA) {
                DeleteStockDeliveryCost::make()->action($cost);

                return;
            }
            $cost->delete();
            EvaluateStockDeliveryCosting::run($stockDelivery);
        });

        foreach ($costs as $costId => $fields) {
            $cost = StockDeliveryCost::withTrashed()->find($costId);
            if (!$cost || $cost->from_service_invoices) {
                continue;
            }
            if ($cost->trashed()) {
                $cost->restore();
            }

            UpdateStockDeliveryCost::make()->action($cost, [
                'label'                  => $fields['label'],
                'category'               => $fields['category'],
                'amount'                 => $fields['amount'],
                'currency_id'            => $fields['currency_id'],
                'exchange'               => $fields['exchange'],
                'is_na'                  => $fields['is_na'],
                'received_at'            => $fields['received_at'],
                'is_waiting_for_invoice' => $fields['is_waiting_for_invoice'],
                'invoice_expected_in_days' => $fields['is_waiting_for_invoice'] && $fields['invoice_expected_at']
                    ? max(0, min(365, (int) today()->diffInDays(Carbon::parse($fields['invoice_expected_at']), false)))
                    : null,
            ]);
        }

        EvaluateStockDeliveryCosting::run($stockDelivery);
    }

    /**
     * A barcode once printed on a label can not be taken back from the log: a person moves it in Goods > Barcodes.
     */
    private function revertTradeUnitProperties(array $target, array $before, array $after): void
    {
        if ($before['barcode_id'] != $after['barcode_id']) {
            throw ValidationException::withMessages(['message' => __('This change gave :code a barcode, which cannot be taken back automatically. Change it in Goods > Barcodes.', ['code' => $target['code']])]);
        }

        UpdateTradeUnit::make()->action(TradeUnit::findOrFail($target['trade_unit_id']), Arr::only($before, GetMcpChangeSnapshot::TRADE_UNIT_FIELDS), strict: false);
    }

    private function revertWebsiteRedirects(array $before): void
    {
        foreach ($before['redirects'] as $redirectId => $toWebpageId) {
            UpdateRedirect::make()->action(Redirect::findOrFail($redirectId), ['to_webpage_id' => $toWebpageId]);
        }
    }

    private function revertMasterPrices(array $before): void
    {
        foreach ($before['prices'] as $masterAssetId => $prices) {
            $updatePrices                    = UpdateMasterAssetPrices::make();
            $updatePrices->forceAsyncCascade = true;
            $updatePrices->action(MasterAsset::findOrFail($masterAssetId), [
                'master_prices' => collect($prices)
                    ->filter(fn (array $price) => (float) $price['value'] > 0)
                    ->map(fn (array $price) => ['value' => (float) $price['value'], 'independent' => $price['independent']])
                    ->all(),
            ]);
        }
    }

    private function revertRelatedProducts(array $target, array $before): void
    {
        if ($target['level'] === 'master') {
            SyncMasterProductCategoryRelatedMasterAssets::make()->action(MasterProductCategory::findOrFail($target['id']), ['master_asset_ids' => $before['ids']]);

            return;
        }

        SyncProductCategoryRelatedProducts::make()->action(ProductCategory::findOrFail($target['id']), ['product_ids' => $before['ids']]);
    }

    private function revertOrgStockAutoOrdering(array $before): void
    {
        foreach ($before['org_stocks'] as $orgStockId => $orgStock) {
            UpdateOrgStock::make()->action(OrgStock::findOrFail($orgStockId), ['is_excluded_from_auto_ordering' => $orgStock['do_not_auto_order']]);
        }
    }

    /**
     * A draft the assistant removed comes back as the same line, so its notes and who added it stay.
     */
    private function revertPartnerShoppingList(array $before, array $after): void
    {
        foreach (array_diff_key($before['lines'], $after['lines']) as $line) {
            $item = PartnerShoppingListItem::withTrashed()->find($line['id']);

            $orderedAgain = $item && PartnerShoppingListItem::where('org_partner_id', $item->org_partner_id)
                ->where('stock_id', $item->stock_id)
                ->whereIn('state', ShoppingListItemStateEnum::onPartnerBuyerList())
                ->exists();

            if (!$item?->trashed() || $item->state !== ShoppingListItemStateEnum::DRAFT || $orderedAgain) {
                throw ValidationException::withMessages(['message' => __('This was changed again after the AI change, so it cannot be reverted automatically. Look at it and fix it by hand.')]);
            }

            $item->restore();
            OrgPartnerHydrateShoppingListItems::dispatch($item->orgPartner);
        }

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

    /**
     * A target the change dated back is put back from that same day, so the sessions it repaid are paid as before,
     * or from the first day not locked for payroll once that day is in a closed week.
     */
    private function revertProductionRecipes(McpChange $mcpChange): void
    {
        foreach ($mcpChange->before['artefacts'] as $artefactId => $steps) {
            $artefact = Artefact::findOrFail($artefactId);
            $timezone = $artefact->organisation->timezone?->name ?? config('app.timezone');
            $changedFrom = ArtefactStepTarget::where('artefact_id', $artefact->id)
                ->whereBetween('created_at', [$mcpChange->data['started_at'] ?? $mcpChange->created_at, $mcpChange->created_at])
                ->get()
                ->groupBy('manufacture_task_id')
                ->map(fn ($targets) => max($targets->min('effective_from')?->setTimezone($timezone)->toDateString(), SetArtefactStepTarget::firstOpenDay($artefact)));
            $steps = array_map(fn (array $step) => $step + array_filter(['effective_from' => $changedFrom[$step['manufacture_task_id']] ?? null]), $steps);

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

    /**
     * Takes off the supplier links the change added, and only the group stock links it created. A supplier product
     * the change created is left in place, as purchase orders may have used it since, but made unavailable so it
     * cannot be ordered while nothing links it. Linking re-syncs a reused supplier product's packing from the SKO,
     * so that packing is put back.
     */
    private function revertSupplierProductLinks(McpChange $mcpChange): void
    {
        $addedLinkIds = array_diff(array_keys($mcpChange->after['links']), array_keys($mcpChange->before['links']));
        $orgStockIds  = OrgStockHasOrgSupplierProduct::whereIn('id', $addedLinkIds)->pluck('org_stock_id')->unique();

        OrgStockHasOrgSupplierProduct::whereIn('id', $addedLinkIds)->delete();

        foreach (Arr::get($mcpChange->data, 'created_stock_supplier_links', []) as $stockHasSupplierProductId) {
            if (!OrgStockHasOrgSupplierProduct::where('stock_has_supplier_product_id', $stockHasSupplierProductId)->exists()) {
                StockHasSupplierProduct::whereKey($stockHasSupplierProductId)->delete();
            }
        }

        foreach (Arr::get($mcpChange->data, 'previous_packing', []) as $supplierProductId => $packing) {
            SupplierProduct::whereKey($supplierProductId)->first()?->update($packing);
        }

        foreach (SupplierProduct::whereIn('id', Arr::get($mcpChange->data, 'created_supplier_product_ids', []))->get() as $supplierProduct) {
            if (!StockHasSupplierProduct::where('supplier_product_id', $supplierProduct->id)->exists()) {
                UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => false]);
            }
        }

        OrgStock::whereIn('id', $orgStockIds)->get()->each->searchable();
    }

    private function revertSupplierProductAvailability(array $before): void
    {
        foreach ($before['org_supplier_products'] as $orgSupplierProductId => $previous) {
            UpdateOrgSupplierProduct::make()->action(OrgSupplierProduct::findOrFail($orgSupplierProductId), ['is_available' => $previous['is_available']]);
        }

        foreach ($before['supplier_products'] as $supplierProductId => $previous) {
            UpdateSupplierProduct::make()->action(SupplierProduct::findOrFail($supplierProductId), ['is_available' => $previous['is_available']]);
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
