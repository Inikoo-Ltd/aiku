<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-12 12:06
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Renames a purchase order from its page heading, for the buyer and for the agent the order was placed through, until
 * it is settled, cancelled or not received. The slug follows the new reference, so the order's URL changes with it;
 * references stay unique within the organisation, follow the format UpdatePurchaseOrder asks for, and every earlier
 * one is kept in data.previous_references with who changed it and when.
 */
class UpdatePurchaseOrderReference extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, string $reference, ?User $user = null): PurchaseOrder
    {
        $previousReference        = $purchaseOrder->reference;
        $purchaseOrder->reference = $reference;

        if (!$purchaseOrder->isDirty('reference')) {
            return $purchaseOrder;
        }

        $data                          = $purchaseOrder->data ?? [];
        $data['previous_references']   = [
            ...($data['previous_references'] ?? []),
            ['reference' => $previousReference, 'by' => $user?->id, 'at' => now()->toIso8601String()],
        ];
        $purchaseOrder->data = $data;

        $purchaseOrder->generateSlug();
        $purchaseOrder->save();

        return $purchaseOrder;
    }

    public static function conflictingPurchaseOrder(PurchaseOrder $purchaseOrder, string $reference): ?PurchaseOrder
    {
        return PurchaseOrder::withTrashed()
            ->where('organisation_id', $purchaseOrder->organisation_id)
            ->where('id', '!=', $purchaseOrder->id)
            ->whereRaw('lower(reference) = lower(?)', [$reference])
            ->first();
    }

    public static function isRenameable(PurchaseOrder $purchaseOrder): bool
    {
        return !in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SETTLED, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED], true);
    }

    public static function canRename(PurchaseOrder $purchaseOrder, ActionRequest $request): bool
    {
        if ($request->user()->authTo("procurement.{$purchaseOrder->organisation_id}.edit")) {
            return true;
        }

        return self::agentOrganisationId($purchaseOrder) !== null
            && $request->user()->authTo('procurement.'.self::agentOrganisationId($purchaseOrder).'.edit');
    }

    public static function agentOrganisationId(PurchaseOrder $purchaseOrder): ?int
    {
        return $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canRename($this->purchaseOrder, $request);
    }

    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Str::slug((string) $value) === '') {
                        $fail(__('The reference needs at least one letter or number.'));

                        return;
                    }

                    $conflict = self::conflictingPurchaseOrder($this->purchaseOrder, (string) $value);
                    if ($conflict) {
                        $fail(__('Another purchase order already uses :reference.', ['reference' => $conflict->reference]));
                    }
                },
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::isRenameable($this->purchaseOrder)) {
            $validator->errors()->add('reference', __('A :state purchase order keeps its reference', [
                'state' => mb_strtolower(PurchaseOrderStateEnum::labels()[$this->purchaseOrder->state->value] ?? $this->purchaseOrder->state->value),
            ]));
        }
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData, ?User $user = null): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData['reference'], $user);
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData['reference'], $request->user());
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): array
    {
        return [
            'reference' => $purchaseOrder->reference,
            'slug'      => $purchaseOrder->slug,
        ];
    }
}
