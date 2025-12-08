<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\Search\PurchaseOrderRecordSearch;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Procurement\WithPrepareDeliveryStoreFields;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StorePurchaseOrder extends OrgAction
{
    use WithNoStrictProcurementOrderRules;
    use WithNoStrictRules;
    use WithPrepareDeliveryStoreFields;

    private OrgSupplier|OrgAgent|OrgPartner $parent;

    public function handle(OrgSupplier|OrgAgent|OrgPartner $parent, array $modelData): PurchaseOrder
    {
        // dd($parent);
        $modelData = $this->prepareDeliveryStoreFields($parent, $modelData);
        if (! Arr::get($modelData, 'reference')) {
            data_set(
                $modelData,
                'reference',
                GetSerialReference::run(
                    container: $parent->organisation,
                    modelType: SerialReferenceModelEnum::PURCHASE_ORDER
                )
            );
        }
        if (! Arr::get($modelData, 'date')) {
            data_set($modelData, 'date', now());
        }
        if (! Arr::get($modelData, 'currency_id')) {
            data_set($modelData, 'currency_id', $parent->organisation->currency_id);
        }
        // dd($parent);
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $parent->purchaseOrders()->create($modelData);

        if (class_basename($parent) == 'OrgSupplier') {
            OrgSupplierHydratePurchaseOrders::dispatch($parent)->delay($this->hydratorsDelay);
            SupplierHydratePurchaseOrders::dispatch($parent->supplier)->delay($this->hydratorsDelay);
        } elseif (class_basename($parent) == 'OrgAgent') {
            OrgAgentHydratePurchaseOrders::dispatch($parent)->delay($this->hydratorsDelay);
            AgentHydratePurchaseOrders::dispatch($parent->agent)->delay($this->hydratorsDelay);
        } elseif (class_basename($parent) == 'OrgPartner') {
            OrgPartnerHydratePurchaseOrders::dispatch($parent)->delay($this->hydratorsDelay);
            OrganisationHydratePurchaseOrders::dispatch($parent->organisation)->delay($this->hydratorsDelay);
        }

        OrganisationHydratePurchaseOrders::dispatch($purchaseOrder->organisation)->delay($this->hydratorsDelay);
        PurchaseOrderRecordSearch::dispatch($purchaseOrder)->delay($this->hydratorsDelay);

        return $purchaseOrder;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash' : 'string',
            ],
            'state' => ['sometimes', 'required', Rule::enum(PurchaseOrderStateEnum::class)],
            'delivery_state' => ['sometimes', 'required', Rule::enum(PurchaseOrderDeliveryStateEnum::class)],
            'cost_items' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_shipping' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_total' => ['sometimes', 'required', 'numeric', 'min:0'],
            'date' => ['sometimes', 'required'],
            'currency_id' => ['sometimes', 'required'],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'purchase_orders',
                extraConditions: [
                    ['column' => 'organisation_id', 'value' => $this->organisation->id],
                ]
            );
        }

        if (! $this->strict) {
            $rules = $this->noStrictStoreRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictPurchaseOrderDatesRules($rules);
        }

        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        $numberPurchaseOrdersStateCreating = $this->parent->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->count();

        if ($this->strict && $numberPurchaseOrdersStateCreating >= 1) {
            $validator->errors()->add('purchase_order', 'Are you sure want to create new purchase order?');
        }

        if ($this->strict && $this->parent->orgSupplierProducts()->where('is_available', true)->count() == 0) {
            $message = match (class_basename($this->parent)) {
                'OrgAgent' => __("Agent don't have any product"),
                'OrgSupplier' => __("Supplier don't have any product"),
                'OrgPartner' => __("Partner don't have any product"),
            };
            $validator->errors()->add('purchase_order', $message);
        }
    }

    public function action(OrgAgent|OrgSupplier|OrgPartner $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): PurchaseOrder
    {
        if (! $audit) {
            PurchaseOrder::disableAuditing();
        }
        $this->asAction = true;
        $this->parent = $parent;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($parent->organisation, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function inOrgAgent(OrgAgent $orgAgent, ActionRequest $request): PurchaseOrder
    {
        $this->parent = $orgAgent;

        $this->initialisation($orgAgent->organisation, $request);

        return $this->handle($orgAgent, $this->validatedData);
    }

    public function inOrgSupplier(OrgSupplier $orgSupplier, ActionRequest $request): PurchaseOrder
    {
        $this->parent = $orgSupplier;
        $this->initialisation($orgSupplier->organisation, $request);

        return $this->handle($orgSupplier, $this->validatedData);
    }

    public function inOrgPartner(OrgPartner $orgPartner, ActionRequest $request): PurchaseOrder
    {
        // dd($orgPartner);
        $this->parent = $orgPartner;
        $this->initialisation($orgPartner->organisation, $request);

        return $this->handle($orgPartner, $this->validatedData);
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($this->parent instanceof OrgAgent) {
            return Redirect::route('grp.org.procurement.org_agents.show.purchase-orders.show', [$purchaseOrder->organisation->slug, $this->parent->slug, $purchaseOrder->slug]);
        } elseif ($this->parent instanceof OrgSupplier) {
            return Redirect::route('grp.org.procurement.org_suppliers.show.purchase-orders.show', [$purchaseOrder->organisation->slug, $this->parent->slug, $purchaseOrder->slug]);
        } else {
            return Redirect::route('grp.org.procurement.org_partners.show.purchase-orders.show', [$purchaseOrder->organisation->slug, $this->parent->slug, $purchaseOrder->slug]);
        }
    }
}
