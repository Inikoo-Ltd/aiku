<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\RepriceStockDeliveryOrgStockMovements;
use App\Actions\GoodsIn\StockDeliveryClaim\StoreStockDeliveryClaim;
use App\Actions\Inventory\OrgStock\Stock\RebuildOrgStockHistoriesSince;
use App\Actions\OrgAction;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Closes a line that arrived under or over what was expected with one of four outcomes. A unit error
 * rewrites what was expected (never what was received); if a real difference is still left after that,
 * the line stays flagged so it can be claimed or accepted.
 */
class ResolveStockDeliveryItemDiscrepancy extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private StockDeliveryItem $stockDeliveryItem;

    public function handle(StockDeliveryItem $stockDeliveryItem, User $user, array $modelData): StockDeliveryItem
    {
        $outcome = StockDeliveryItemDiscrepancyOutcomeEnum::from($modelData['outcome']);

        DB::transaction(function () use ($stockDeliveryItem, $user, $modelData, $outcome) {
            if ($stockDeliveryItem->claim?->state === StockDeliveryClaimStateEnum::OPEN && $outcome !== StockDeliveryItemDiscrepancyOutcomeEnum::SUPPLIER_CLAIM) {
                $stockDeliveryItem->claim->delete();
                $stockDeliveryItem->unsetRelation('claim');
            }

            match ($outcome) {
                StockDeliveryItemDiscrepancyOutcomeEnum::RECOUNT_REQUESTED    => $this->requestRecount($stockDeliveryItem, $user, $modelData),
                StockDeliveryItemDiscrepancyOutcomeEnum::UNIT_ERROR_CORRECTED => $this->correctUnitError($stockDeliveryItem, $user, $modelData),
                StockDeliveryItemDiscrepancyOutcomeEnum::SUPPLIER_CLAIM       => $this->claim($stockDeliveryItem, $user, $modelData),
                StockDeliveryItemDiscrepancyOutcomeEnum::SURPLUS_ACCEPTED     => $this->setOutcome($stockDeliveryItem, $user, $outcome),
            };
        });

        StockDeliveriesHydrateItems::run($stockDeliveryItem->stockDelivery);

        return $stockDeliveryItem->refresh();
    }

    private function setOutcome(StockDeliveryItem $stockDeliveryItem, User $user, ?StockDeliveryItemDiscrepancyOutcomeEnum $outcome, array $data = []): void
    {
        $stockDeliveryItem->update([
            'discrepancy_outcome'        => $outcome,
            'discrepancy_resolved_at'    => $outcome?->isResolved() ? now() : null,
            'discrepancy_resolved_by_id' => $outcome?->isResolved() ? $user->id : null,
            'data'                       => array_merge($stockDeliveryItem->data ?? [], $data),
        ]);
    }

    private function requestRecount(StockDeliveryItem $stockDeliveryItem, User $user, array $modelData): void
    {
        $stockDelivery = $stockDeliveryItem->stockDelivery;
        $orgStock      = $stockDeliveryItem->orgStock;

        $task = StoreStaffTask::make()->action($user, [
            'subject'     => __('Recount :code from delivery :reference', ['code' => $orgStock?->code, 'reference' => $stockDelivery->reference]),
            'description' => __(':expected units were expected and :received were counted at goods in. Please count what arrived again and answer here with the count.', [
                'expected' => (float) $stockDeliveryItem->unit_quantity,
                'received' => (float) $stockDeliveryItem->unit_quantity_checked,
            ]).(Arr::get($modelData, 'notes') ? "\n".$modelData['notes'] : ''),
            'department'  => 'warehouse',
            'model_type'  => $orgStock ? 'OrgStock' : null,
            'model_id'    => $orgStock?->id,
        ]);

        $this->setOutcome($stockDeliveryItem, $user, StockDeliveryItemDiscrepancyOutcomeEnum::RECOUNT_REQUESTED, ['recount_staff_task_id' => $task->id]);
    }

    private function correctUnitError(StockDeliveryItem $stockDeliveryItem, User $user, array $modelData): void
    {
        $unitQuantity = (float) $modelData['unit_quantity'];
        $netAmount    = (float) Arr::get($modelData, 'net_amount', $stockDeliveryItem->net_amount);
        $orgExchange  = (float) ($stockDeliveryItem->org_exchange ?? 1);
        $grpExchange  = (float) ($stockDeliveryItem->grp_exchange ?? 1);

        $correction = [
            'unit_quantity' => (float) $stockDeliveryItem->unit_quantity,
            'net_amount'    => (float) $stockDeliveryItem->net_amount,
            'by'            => $user->id,
            'at'            => now()->toIso8601String(),
        ];

        $isCostItemsFromNet = $stockDeliveryItem->cost_items === null || EvaluateStockDeliveryCosting::cents((float) $stockDeliveryItem->cost_items) === EvaluateStockDeliveryCosting::cents((float) $stockDeliveryItem->net_amount);

        $stockDeliveryItem->update([
            'unit_quantity'    => $unitQuantity,
            'net_amount'       => $netAmount,
            'gross_amount'     => $netAmount,
            'org_net_amount'   => $netAmount * $orgExchange,
            'org_gross_amount' => $netAmount * $orgExchange,
            'grp_net_amount'   => $netAmount * $grpExchange,
            'grp_gross_amount' => $netAmount * $grpExchange,
            ...($isCostItemsFromNet ? ['cost_items' => $netAmount] : []),
        ]);

        $stillFlagged = StockDeliveryItemDiscrepancyEnum::isFlagged($stockDeliveryItem->refresh()->discrepancy());

        $this->setOutcome(
            $stockDeliveryItem,
            $user,
            $stillFlagged ? null : StockDeliveryItemDiscrepancyOutcomeEnum::UNIT_ERROR_CORRECTED,
            ['unit_corrections' => [...Arr::get($stockDeliveryItem->data, 'unit_corrections', []), $correction]]
        );

        $stockDelivery = $stockDeliveryItem->stockDelivery;
        if ($stockDelivery->costs()->exists()) {
            EvaluateStockDeliveryCosting::run($stockDelivery);
        }

        foreach (RepriceStockDeliveryOrgStockMovements::run($stockDelivery, $stockDeliveryItem) as $orgStockId => $fromDate) {
            RebuildOrgStockHistoriesSince::dispatch($orgStockId, $fromDate)->delay(60)->afterCommit();
        }
    }

    private function claim(StockDeliveryItem $stockDeliveryItem, User $user, array $modelData): void
    {
        $missing = (float) $stockDeliveryItem->unit_quantity - (float) $stockDeliveryItem->unit_quantity_checked;
        $quantity = (float) Arr::get($modelData, 'claim_quantity', $missing);

        if ($stockDeliveryItem->claim) {
            $stockDeliveryItem->claim->update(Arr::only($modelData, ['notes']));
        } else {
            StoreStockDeliveryClaim::run($stockDeliveryItem, $user, [
                'quantity' => $quantity,
                'amount'   => Arr::get($modelData, 'claim_amount', round($quantity * (float) $stockDeliveryItem->net_amount / max((float) $stockDeliveryItem->unit_quantity, 0.0001), 2)),
                'notes'    => Arr::get($modelData, 'notes'),
                'photos'   => Arr::get($modelData, 'photos', []),
            ]);
        }

        $this->setOutcome($stockDeliveryItem, $user, StockDeliveryItemDiscrepancyOutcomeEnum::SUPPLIER_CLAIM);
    }

    public function rules(): array
    {
        return [
            'outcome'        => ['required', Rule::enum(StockDeliveryItemDiscrepancyOutcomeEnum::class)],
            'unit_quantity'  => ['required_if:outcome,unit_error_corrected', 'nullable', 'numeric', 'gt:0'],
            'net_amount'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'claim_quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'claim_amount'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:5000'],
            'photos'         => ['sometimes', 'array', 'max:10'],
            'photos.*'       => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:20480'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $item          = $this->stockDeliveryItem;
        $stockDelivery = $item->stockDelivery;
        $outcome       = StockDeliveryItemDiscrepancyOutcomeEnum::tryFrom((string) $this->get('outcome'));

        if (!$item->checked_at || (float) $item->unit_quantity_checked === (float) $item->unit_quantity) {
            $validator->errors()->add('outcome', __('This line arrived as expected'));

            return;
        }

        if ($item->claim && !$item->claim->state->isClosed() && $item->claim->state !== StockDeliveryClaimStateEnum::OPEN && $outcome !== StockDeliveryItemDiscrepancyOutcomeEnum::SUPPLIER_CLAIM) {
            $validator->errors()->add('outcome', __('The claim was already sent to the supplier; close it first'));
        }

        match ($outcome) {
            StockDeliveryItemDiscrepancyOutcomeEnum::SURPLUS_ACCEPTED => (float) $item->unit_quantity_checked <= (float) $item->unit_quantity
                ? $validator->errors()->add('outcome', __('Only a line that arrived over what was expected has a surplus'))
                : null,
            StockDeliveryItemDiscrepancyOutcomeEnum::SUPPLIER_CLAIM => (float) $item->unit_quantity_checked >= (float) $item->unit_quantity && !$item->claim
                ? $validator->errors()->add('outcome', __('Only a line that arrived short can be claimed'))
                : null,
            StockDeliveryItemDiscrepancyOutcomeEnum::UNIT_ERROR_CORRECTED => $stockDelivery->is_costed
                ? $validator->errors()->add('outcome', __('The delivery is costed: reopen the costing before correcting what was expected'))
                : null,
            default => null,
        };
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, $request->user(), $this->validatedData);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, User $user, array $modelData): StockDeliveryItem
    {
        $this->asAction          = true;
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public static function recountTask(StockDeliveryItem $stockDeliveryItem): ?StaffTask
    {
        $taskId = Arr::get($stockDeliveryItem->data, 'recount_staff_task_id');

        return $taskId ? StaffTask::find($taskId) : null;
    }
}
