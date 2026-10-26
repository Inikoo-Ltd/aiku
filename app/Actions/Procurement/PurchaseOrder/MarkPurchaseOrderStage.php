<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Records the date of a purchase order stage. Stages go in order: skipping one, completing a stage without its
 * gate, backdating or clearing a date needs a reason, written to the order's history. Any stage past submitted
 * means the supplier took the order, so a submitted order is confirmed, unless a price or quantity change is still unanswered.
 * Agents mark only their own stages, on their own orders.
 */
class MarkPurchaseOrderStage extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    private bool $actingAsAgent = false;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user()->authTo("procurement.{$this->organisation->id}.edit")) {
            return true;
        }

        $agentOrganisationId = $this->purchaseOrder->isAgentOrder() ? $this->purchaseOrder->agent?->organisation_id : null;
        if ($agentOrganisationId && $request->user()->authTo("procurement.$agentOrganisationId.edit")) {
            $this->actingAsAgent = true;

            return true;
        }

        return false;
    }

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $field = $modelData['field'];
        $date  = $this->stamp(Arr::get($modelData, 'date'));
        $old   = $purchaseOrder->{$field}?->toDateString();

        if (self::confirmsOrder($purchaseOrder, $date) && !$purchaseOrder->isWaitingForLineAnswers()) {
            $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder);
            $purchaseOrder->update(['confirmed_at' => $date->min(now())]);
        }

        $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, [$field => $date]);
        BroadcastAgentIncomingPurchaseOrdersChanged::dispatchFor($purchaseOrder);

        if ($reason = Arr::get($modelData, 'reason')) {
            self::auditOverride($purchaseOrder, $field, $old, $date?->toDateString(), $reason);
        }

        return $purchaseOrder;
    }

    public static function confirmsOrder(PurchaseOrder $purchaseOrder, mixed $date): bool
    {
        return $date !== null && $purchaseOrder->state === PurchaseOrderStateEnum::SUBMITTED;
    }

    public static function auditOverride(PurchaseOrder $purchaseOrder, string $field, ?string $old, ?string $new, string $reason): void
    {
        $purchaseOrder->auditEvent     = 'stage_override';
        $purchaseOrder->isCustomEvent  = true;
        $purchaseOrder->auditCustomOld = [$field => $old];
        $purchaseOrder->auditCustomNew = [$field => $new, 'reason' => $reason];
        Event::dispatch(new AuditCustom($purchaseOrder));
        $purchaseOrder->isCustomEvent  = false;
        $purchaseOrder->auditCustomOld = $purchaseOrder->auditCustomNew = [];
    }

    /**
     * Why this mark needs a reason, empty when it does not.
     *
     * @return string[]
     */
    public function reasonsNeeded(PurchaseOrder $purchaseOrder, string $field, ?string $date): array
    {
        if ($date === null) {
            return $purchaseOrder->{$field} === null ? [] : [__('the date is cleared')];
        }

        $reasons = [];
        if (!in_array($field, PurchaseOrderStageEnum::PLANNED_FIELDS) && Carbon::parse($date)->toDateString() < Carbon::parse(self::today($purchaseOrder->organisation))->subDay()->toDateString()) {
            $reasons[] = __('the date is more than a day in the past');
        }

        $skipped = GetPurchaseOrderStages::make()->skippedBy($purchaseOrder, $field, GetPurchaseOrderStages::run($purchaseOrder));
        if ($skipped) {
            $reasons[] = __('not recorded yet: :stages', ['stages' => implode(', ', $skipped)]);
        }

        return $reasons;
    }

    public function rules(): array
    {
        $fields = $this->actingAsAgent ? PurchaseOrderStageEnum::AGENT_MARKABLE_FIELDS : array_keys(PurchaseOrderStageEnum::MARKABLE_FIELDS);

        return [
            'field'  => ['required', Rule::in($fields)],
            'date'   => in_array($this->get('field'), PurchaseOrderStageEnum::PLANNED_FIELDS)
                ? ['present', 'nullable', 'date']
                : ['present', 'nullable', 'date', 'before_or_equal:'.Carbon::parse(self::today($this->organisation))->addDay()->toDateString()],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (in_array($this->purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED])) {
            $validator->errors()->add('field', __('Only a submitted order goes through its stages'));

            return;
        }

        if ($this->get('field') === 'handed_over_at' && $this->get('date') !== null && StoreStockDeliveryFromPurchaseOrder::shipsFromAgentWarehouse($this->purchaseOrder)) {
            $validator->errors()->add('field', __('The agent receives these goods in its own warehouse: record them with a goods receipt, they are shipped when their container is dispatched'));

            return;
        }

        $reasons = $this->reasonsNeeded($this->purchaseOrder, $this->get('field'), $this->get('date'));
        if ($reasons && !trim((string) $this->get('reason'))) {
            $validator->errors()->add('reason', __('Give a reason: :reasons', ['reasons' => implode('; ', $reasons)]));
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

        $field         = $this->validatedData['field'];
        $purchaseOrder = $this->handle($purchaseOrder, $this->validatedData);

        if ($field === 'qc_passed_at' && $purchaseOrder->qc_passed_at) {
            NotifyPurchaseOrderBalanceDue::run($purchaseOrder, $request->user());
        }

        $label = PurchaseOrderStageEnum::fieldLabels()[$field];
        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), $purchaseOrder->{$field}
            ? __(':stage on :date', ['stage' => $label, 'date' => $purchaseOrder->{$field}->toDateString()])
            : __(':stage cleared', ['stage' => $label]));

        return $purchaseOrder;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public static function today(Organisation $organisation): string
    {
        return now($organisation->timezone?->name)->toDateString();
    }

    private function stamp(?string $date): ?Carbon
    {
        if ($date === null) {
            return null;
        }

        return Carbon::parse($date)->toDateString() === self::today($this->organisation) ? now() : Carbon::parse($date);
    }
}
