<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Records the date of a purchase order stage. A person (the PO page and the PO journey board, gated()) marks only the
 * stage the order is at or an earlier one: a submitted order first needs its terms agreed (deposit paid or no deposit
 * needed), and a later stage waits for the one before it to be done or skipped (INI-239). Completing a stage without
 * its gate, backdating or clearing a date needs a reason, written to the order's history. Automatic paths (goods
 * receipts, container lists) are not gated, and their mark of a stage past submitted still confirms a submitted order,
 * unless a price or quantity change is still unanswered. Agents mark only their own stages, on their own orders.
 */
class MarkPurchaseOrderStage extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    private bool $actingAsAgent = false;

    private bool $gated = false;

    public function gated(): static
    {
        $this->gated = true;

        return $this;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user()->authTo("procurement.{$this->organisation->id}.edit")) {
            return true;
        }

        $this->actingAsAgent = self::isAgentStaff($request->user(), $this->purchaseOrder);

        return $this->actingAsAgent;
    }

    /**
     * Whether this user marks the order as the agent's staff: they can edit procurement in the agent's organisation
     * and not in the buyer's organisation. The one answer to it, for the order page and
     * the PO journey board alike.
     */
    public static function isAgentStaff(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null
            && $user->authTo("procurement.$agentOrganisationId.edit")
            && !$user->authTo("procurement.{$purchaseOrder->organisation_id}.edit");
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
        if (in_array($field, PurchaseOrderStageEnum::READY_CHECK_FIELDS)) {
            $purchaseOrder = RecordPurchaseOrderHandedOverWhenReady::run($purchaseOrder);
        }
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

        if ($this->get('field') === 'handed_over_at' && $this->get('date') !== null && $this->purchaseOrder->choMode() === 'next_container') {
            $validator->errors()->add('field', __('The agent receives these goods in its own warehouse: record them with a goods receipt, they are shipped when their container is dispatched'));

            return;
        }

        if ($this->gated && $this->get('date') !== null && $this->purchaseOrder->{$this->get('field')} === null) {
            $stages  = GetPurchaseOrderStages::run($this->purchaseOrder);
            $blocked = GetPurchaseOrderStages::make()->blockedReason($this->purchaseOrder, PurchaseOrderStageEnum::MARKABLE_FIELDS[$this->get('field')], $stages);
            if ($blocked) {
                $validator->errors()->add('field', $blocked);

                return;
            }
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
        $this->gated         = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        $field         = $this->validatedData['field'];
        $purchaseOrder = $this->handle($purchaseOrder, $this->validatedData);

        if ($field === 'qc_passed_at' && $purchaseOrder->qc_passed_at) {
            NotifyPurchaseOrderBalanceDue::run($purchaseOrder, $request->user());
        }

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => $purchaseOrder->{$field}
            ? __(':stage on :date', ['stage' => PurchaseOrderStageEnum::fieldLabels()[$field], 'date' => $purchaseOrder->{$field}->toDateString()])
            : __(':stage cleared', ['stage' => PurchaseOrderStageEnum::fieldLabels()[$field]]));

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
