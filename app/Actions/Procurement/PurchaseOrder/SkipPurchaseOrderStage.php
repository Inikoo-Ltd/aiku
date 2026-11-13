<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 09:00:00 Central European Standard Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Skips a purchase order step or stage (PurchaseOrderStageEnum::SKIPS), or undoes the skip, with a reason kept in the
 * order's history, so an order going strictly through its stages never gets stuck (INI-239). Agents skip only QC, on
 * their own orders (PurchaseOrderStageEnum::AGENT_SKIPPABLE). Like a mark, a skip is
 * only for the stage the order is at or an earlier one. Handed over without checks also records the hand over date,
 * so every board reads the order as handed over; undoing it clears that date.
 */
class SkipPurchaseOrderStage extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user()->authTo("procurement.{$this->organisation->id}.edit")) {
            return true;
        }

        return MarkPurchaseOrderStage::isAgentStaff($request->user(), $this->purchaseOrder)
            && in_array($request->input('key'), PurchaseOrderStageEnum::AGENT_SKIPPABLE, true);
    }

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $key     = $modelData['key'];
        $skipped = (bool) $modelData['skipped'];
        $had     = $purchaseOrder->stageSkip($key) !== null;
        $data    = $purchaseOrder->data ?? [];

        if ($skipped) {
            Arr::set($data, "stage_skips.$key", [
                'user_id' => request()->user()?->id,
                'at'      => now()->toIso8601String(),
                'reason'  => $modelData['reason'],
            ]);
        } else {
            Arr::forget($data, "stage_skips.$key");
        }
        $purchaseOrder->update(['data' => $data]);

        if ($key === 'handed_over') {
            $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, ['handed_over_at' => $skipped ? ($purchaseOrder->handed_over_at ?? now()) : null]);
        }

        MarkPurchaseOrderStage::auditOverride($purchaseOrder, "skip_$key", $had ? 'skipped' : null, $skipped ? 'skipped' : null, $modelData['reason']);

        if ($skipped && in_array($key, ['qc', 'compliance', 'sample'])) {
            $purchaseOrder = RecordPurchaseOrderHandedOverWhenReady::run($purchaseOrder);
        }
        BroadcastAgentIncomingPurchaseOrdersChanged::dispatchFor($purchaseOrder);

        return $purchaseOrder;
    }

    public function rules(): array
    {
        return [
            'key'     => ['required', Rule::in(array_keys(PurchaseOrderStageEnum::SKIPS))],
            'skipped' => ['required', 'boolean'],
            'reason'  => ['required', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (!in_array($this->purchaseOrder->state, PurchaseOrderStateEnum::open())) {
            $validator->errors()->add('key', __('Only an open order skips its stages'));

            return;
        }

        $key = $this->get('key');
        if ($this->get('skipped') && $key === 'handed_over' && $this->purchaseOrder->choMode() === 'next_container') {
            $validator->errors()->add('key', __('The agent receives these goods in its own warehouse: they are handed over when every item is in a container'));

            return;
        }

        if ($this->get('skipped')) {
            $getStages = GetPurchaseOrderStages::make();
            $stages    = $getStages->handle($this->purchaseOrder);
            if ($getStages->isSkipRecorded($this->purchaseOrder, $key, $stages)) {
                $validator->errors()->add('key', __('Already recorded: there is nothing to skip'));

                return;
            }
            $blocked = $getStages->blockedReason($this->purchaseOrder, PurchaseOrderStageEnum::SKIPS[$key], $stages);
            if ($blocked) {
                $validator->errors()->add('key', $blocked);
            }
        }
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
