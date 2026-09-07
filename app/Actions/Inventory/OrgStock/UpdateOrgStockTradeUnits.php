<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Edits one warehouse's OS-TU composition (its packed_in reality) and pushes the change
 * through the same pipes the stock editor uses: org stock pivot, packed_in, the products'
 * pick mapping and any delivery note items still ahead of picking.
 *
 * A pivot change re-means every count stored against this SKO, so with stock in locations
 * the editor must decide what the counts now mean: keep them (locations get flagged for a
 * physical recount) or convert them arithmetically to the new pack size. Conversion is a
 * pure change of counting unit — the shelf and its monetary value do not move — so it is
 * booked as zero-valued audit movements.
 */
class UpdateOrgStockTradeUnits extends OrgAction
{
    use WithGoodsEditAuthorisation;

    private ?int $actingUserId = null;

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        $tradeUnitsData = collect($modelData['trade_units'])
            ->mapWithKeys(fn ($tradeUnit) => [$tradeUnit['id'] => ['quantity' => $tradeUnit['quantity']]])
            ->toArray();

        $stockStrategy    = Arr::get($modelData, 'stock_strategy');
        $stockedLocations = $orgStock->locationOrgStocks()->where('quantity', '!=', 0)->with('location')->get();
        $stockedChange    = $stockedLocations->isNotEmpty()
            && SyncOrgStockTradeUnits::pivotChanges($orgStock, $tradeUnitsData);

        if (!$stockedChange) {
            $stockStrategy = null;
        }

        $conversionRatio = $stockedChange ? $this->conversionRatio($orgStock, $tradeUnitsData) : null;

        if ($stockedChange && !in_array($stockStrategy, ['keep', 'convert'])) {
            $errors = [
                'stock_recount_required' => __(
                    ':code holds stock in :count warehouse locations. Changing its packing re-means those counts: decide whether to keep them (locations get flagged for recount) or convert them to the new packing.',
                    ['code' => $orgStock->code, 'count' => $stockedLocations->count()]
                ),
            ];
            if ($conversionRatio) {
                $errors['stock_conversion_preview'] = $stockedLocations
                    ->map(fn ($locationOrgStock) => $locationOrgStock->location->code.': '
                        .(float) $locationOrgStock->quantity.' → '
                        .round($locationOrgStock->quantity * $conversionRatio, 3))
                    ->implode("\n");
            }

            throw ValidationException::withMessages($errors);
        }

        if ($stockStrategy === 'convert' && !$conversionRatio) {
            throw ValidationException::withMessages([
                'stock_strategy' => __('The stored counts cannot be converted arithmetically for this composition; keep them and recount instead.'),
            ]);
        }

        $orgStock = DB::transaction(function () use ($orgStock, $tradeUnitsData, $stockStrategy, $stockedLocations, $conversionRatio) {
            $orgStock = SyncOrgStockTradeUnits::run($orgStock, $tradeUnitsData, $stockStrategy);

            if ($stockStrategy === 'convert') {
                foreach ($stockedLocations as $locationOrgStock) {
                    $convertedQuantity = round($locationOrgStock->quantity * $conversionRatio, 3);
                    StoreOrgStockMovement::make()->action($orgStock, $locationOrgStock->location, [
                        'quantity'         => $convertedQuantity - $locationOrgStock->quantity,
                        'audited_quantity' => $convertedQuantity,
                        'type'             => OrgStockMovementTypeEnum::AUDIT,
                        'reason'           => OrgStockMovementReasonEnum::UOM,
                        'note'             => __('Count converted after packing change'),
                        'org_amount'       => 0,
                        'user_id'          => $this->actingUserId,
                        'date'             => now()->format('Y-m-d H:i:s.u'),
                    ]);
                }
            }

            return $orgStock;
        });

        $orgStock->unsetRelation('products');
        $jobs = $orgStock->products
            ->map(fn ($product) => SyncProductOrgStocksFromTradeUnits::makeJob($product))
            ->push(SyncDeliveryNoteItemsRequiredPickQuantity::makeJob($orgStock))
            ->all();

        Bus::chain($jobs)->dispatch();

        return $orgStock;
    }

    /**
     * Physical pieces on the shelf don't move when the packing is redeclared, so the count
     * converts by old/new pack size. Only defined where both sides are a real packing under
     * the same rule the packed_in hydrators use: a single trade unit with a clean integer
     * quantity between 1 and 50000 — anything else is "packing unknown" and must be counted.
     */
    private function conversionRatio(OrgStock $orgStock, array $tradeUnitsData): ?float
    {
        $currentPivot = $orgStock->tradeUnits()->pluck('model_has_trade_units.quantity', 'trade_units.id');
        $newPivot     = collect($tradeUnitsData)->map(fn ($pivotData) => (float) $pivotData['quantity']);

        if ($currentPivot->count() !== 1 || $newPivot->count() !== 1
            || (int) $currentPivot->keys()->first() !== (int) $newPivot->keys()->first()
        ) {
            return null;
        }

        $oldPackSize = (float) $currentPivot->first();
        $newPackSize = (float) $newPivot->first();

        foreach ([$oldPackSize, $newPackSize] as $packSize) {
            if (floor($packSize) != $packSize || $packSize <= 0 || $packSize > 50000) {
                return null;
            }
        }

        return $oldPackSize !== $newPackSize ? $oldPackSize / $newPackSize : null;
    }

    public function rules(): array
    {
        return [
            'trade_units'            => ['required', 'array'],
            'trade_units.*.id'       => ['required', 'exists:trade_units,id'],
            'trade_units.*.quantity' => ['required', 'numeric', 'gt:0'],
            'stock_strategy'         => ['sometimes', 'nullable', 'in:keep,convert'],
        ];
    }

    public function asController(OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->actingUserId = $request->user()?->id;
        $this->initialisationFromGroup($orgStock->group, $request);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
