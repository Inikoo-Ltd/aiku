<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithNoStrictProcurementOrderRules;
    use HasPurchaseOrderHydrators;

    private PurchaseOrder $purchaseOrder;

    private bool $actingAsAgent = false;

    /**
     * An agent order's clean handover is judged by management, never by the agent or the buyer.
     */
    public const array MANAGEMENT_ONLY_FIELDS = [
        'approved_ready_at',
        'handed_over_at',
        'qc_passed_at',
        'compliance_complete_at',
        'chs_excluded',
        'chs_exclusion_reason',
    ];

    public const array AGENT_FIELDS = [
        'proposed_ready_at',
        'deposit_amount',
        'deposit_paid_at',
        'sample_approved_at',
        'produced_at',
        'estimated_production_date',
        'estimated_receiving_date',
    ];

    private const DATA_FIELDS = [
        'delivery_type',
        'incoterm',
        'port_of_export',
        'port_of_import',
        'delivery_address',
        'payment_terms',
        'terms_and_conditions',
        'estimated_production_date',
        'estimated_receiving_date',
    ];

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        if (array_key_exists('estimated_receiving_date', $modelData)) {
            $modelData['estimated_receiving_date'] = $modelData['estimated_receiving_date'] ?: null;
            $modelData['estimated_received_at']    = $modelData['estimated_receiving_date'];
        }

        foreach (self::DATA_FIELDS as $field) {
            if (array_key_exists($field, $modelData)) {
                $modelData['data'][$field] = Arr::pull($modelData, $field);
            }
        }

        $purchaseOrder = $this->update($purchaseOrder, $modelData, ['data']);

        if ($purchaseOrder->wasChanged(['state', 'delivery_state'])) {
            $this->purchaseOrderHydrate($purchaseOrder);
        }

        return $purchaseOrder;
    }

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

    public function rules(): array
    {
        $rules = [
            'reference'       => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash:ascii' : 'string',
            ],
            'notes' => ['sometimes', 'string'],
            'delivery_type'        => ['sometimes', 'nullable', 'string', 'in:parcel,container'],
            'incoterm'             => ['sometimes', 'nullable', 'string'],
            'port_of_export'       => ['sometimes', 'nullable', 'string'],
            'port_of_import'       => ['sometimes', 'nullable', 'string'],
            'delivery_address'     => ['sometimes', 'nullable', 'string'],
            'payment_terms'        => ['sometimes', 'nullable', 'string'],
            'terms_and_conditions' => ['sometimes', 'nullable', 'string'],
            'estimated_production_date' => ['sometimes', 'nullable', 'date'],
            'estimated_receiving_date'  => ['sometimes', 'nullable', 'date'],
            'estimated_delivery_days'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'deposit_amount'            => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'deposit_paid_at'           => ['sometimes', 'nullable', 'date'],
            'balance_paid_at'           => ['sometimes', 'nullable', 'date'],
            'sample_approved_at'        => ['sometimes', 'nullable', 'date'],
            'produced_at'               => ['sometimes', 'nullable', 'date'],
            'qc_passed_at'              => ['sometimes', 'nullable', 'date'],
            'handed_over_at'            => ['sometimes', 'nullable', 'date'],
            'buyer_id'                  => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'proposed_ready_at'         => ['sometimes', 'nullable', 'date'],
            'approved_ready_at'         => ['sometimes', 'nullable', 'date'],
            'compliance_complete_at'    => ['sometimes', 'nullable', 'date'],
            'chs_excluded'              => ['sometimes', 'boolean'],
            'chs_exclusion_reason'      => ['sometimes', 'nullable', 'string'],
        ];

        if ($this->actingAsAgent) {
            return Arr::only($rules, self::AGENT_FIELDS);
        }

        if (!$this->asAction && $this->purchaseOrder->isAgentOrder() && request()->user()->authorisedShopOrganisations()->doesntExist()) {
            $rules = Arr::except($rules, self::MANAGEMENT_ONLY_FIELDS);
        }

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'purchase_orders',
                extraConditions: [
                    [
                        'column' => 'organisation_id',
                        'value'  => $this->organisation->id,
                    ],
                    [
                        'column'   => 'id',
                        'operator' => '!=',
                        'value'    => $this->purchaseOrder->id
                    ]
                ]
            );
        }



        if (!$this->strict) {
            $rules['state']          = ['sometimes', Rule::enum(PurchaseOrderStateEnum::class)];
            $rules['delivery_state'] = ['sometimes', Rule::enum(PurchaseOrderDeliveryStateEnum::class)];
            $rules['cost_items']     = ['sometimes', 'numeric'];
            $rules['cost_extra']     = ['sometimes', 'numeric'];
            $rules['cost_shipping']  = ['sometimes', 'numeric'];
            $rules['cost_duties']    = ['sometimes', 'numeric'];
            $rules['cost_tax']       = ['sometimes', 'numeric'];
            $rules['cost_total']     = ['sometimes', 'numeric'];

            $rules = $this->noStrictUpdateRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictPurchaseOrderDatesRules($rules);

        }

        return $rules;
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): PurchaseOrder
    {
        if (!$audit) {
            PurchaseOrder::disableAuditing();
        }
        $this->asAction      = true;
        $this->strict        = $strict;
        $this->purchaseOrder = $purchaseOrder;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;

        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
