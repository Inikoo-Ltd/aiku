<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-12 12:06
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Models\Procurement\PurchaseOrder;
use Closure;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

/**
 * Renames a purchase order from its page heading, for the buyer and for the agent the order was placed through. The
 * slug follows the new reference, so the order's URL changes with it; references stay unique within the organisation.
 */
class UpdatePurchaseOrderReference extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, string $reference): PurchaseOrder
    {
        $purchaseOrder->reference = $reference;

        if (!$purchaseOrder->isDirty('reference')) {
            return $purchaseOrder;
        }

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
        return self::canRename($this->purchaseOrder, $request);
    }

    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:255',
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

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData['reference']);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): array
    {
        return [
            'reference' => $purchaseOrder->reference,
            'slug'      => $purchaseOrder->slug,
        ];
    }
}
