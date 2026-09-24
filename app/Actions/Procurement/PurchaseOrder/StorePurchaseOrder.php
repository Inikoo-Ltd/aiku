<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Procurement\WithPrepareDeliveryStoreFields;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurchaseOrders;
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
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StorePurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithPrepareDeliveryStoreFields;
    use WithNoStrictRules;
    use WithNoStrictProcurementOrderRules;

    private OrgSupplier|OrgAgent|OrgPartner $parent;

    public function handle(OrgSupplier|OrgAgent|OrgPartner $parent, array $modelData): PurchaseOrder
    {
        $modelData = $this->prepareDeliveryStoreFields($parent, $modelData);
        $deliveryAddress = ResolvePurchaseOrderDeliveryAddress::run(
            $parent->organisation,
            Arr::get($modelData, 'data.delivery_address')
        );

        if ($deliveryAddress) {
            data_set($modelData, 'data.delivery_address', $deliveryAddress);
        }

        if (!Arr::get($modelData, 'reference')) {
            data_set($modelData, 'reference', $this->getNewReference($parent));
        }
        if (!Arr::get($modelData, 'date')) {
            data_set($modelData, 'date', now());
        }
        if (!Arr::get($modelData, 'currency_id')) {
            data_set($modelData, 'currency_id', $parent->organisation->currency_id);
        }
        if (!array_key_exists('buyer_id', $modelData) && auth()->user() instanceof User) {
            data_set($modelData, 'buyer_id', auth()->id());
        }

        $currency = Currency::find($modelData['currency_id']);
        $date     = Carbon::parse($modelData['date'])->startOfDay();
        data_set($modelData, 'org_exchange', GetHistoricCurrencyExchange::run($currency, $parent->organisation->currency, $date), overwrite: false);
        data_set($modelData, 'grp_exchange', GetHistoricCurrencyExchange::run($currency, $parent->organisation->group->currency, $date), overwrite: false);
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $parent->purchaseOrders()->create($modelData);
        $purchaseOrder->refresh();

        if ($parent instanceof OrgSupplier) {
            OrgSupplierHydratePurchaseOrders::dispatch($parent)->delay($this->hydratorsDelay);
            SupplierHydratePurchaseOrders::dispatch($parent->supplier)->delay($this->hydratorsDelay);
        } elseif ($parent instanceof OrgAgent) {
            OrgAgentHydratePurchaseOrders::dispatch($parent)->delay($this->hydratorsDelay);
            AgentHydratePurchaseOrders::dispatch($parent->agent)->delay($this->hydratorsDelay);
        } elseif ($parent instanceof OrgPartner) {
            OrgPartnerHydratePurchaseOrders::dispatch($parent)->delay($this->hydratorsDelay);
        }

        OrganisationHydratePurchaseOrders::dispatch($purchaseOrder->organisation)->delay($this->hydratorsDelay);
        GroupHydratePurchaseOrders::dispatch($purchaseOrder->group)->delay($this->hydratorsDelay);

        return $purchaseOrder;
    }

    private function getNewReference(OrgSupplier|OrgAgent|OrgPartner $parent): string
    {
        $container = $parent instanceof OrgPartner || !$parent->purchaseOrderSerialReference ? $parent->organisation : $parent;

        do {
            $reference = GetSerialReference::run(
                container: $container,
                modelType: SerialReferenceModelEnum::PURCHASE_ORDER
            );
        } while ($parent->organisation->purchaseOrders()->where('reference', $reference)->exists());

        return $reference;
    }

    public function rules(): array
    {
        $rules = [
            'reference'      => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash' : 'string'
            ],
            'state'          => ['sometimes', 'required', Rule::enum(PurchaseOrderStateEnum::class)],
            'delivery_state' => ['sometimes', 'required', Rule::enum(PurchaseOrderDeliveryStateEnum::class)],
            'cost_items'     => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_shipping'  => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_total'     => ['sometimes', 'required', 'numeric', 'min:0'],
            'date'           => ['sometimes', 'required'],
            'currency_id'    => ['sometimes', 'required'],
            'buyer_id'       => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'purchase_orders',
                extraConditions: [
                    ['column' => 'organisation_id', 'value' => $this->organisation->id],
                ]
            );
        }


        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictPurchaseOrderDatesRules($rules);
        }

        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->strict) {
            return;
        }

        $openPurchaseOrder = $this->parent->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first();
        if ($openPurchaseOrder) {
            $validator->errors()->add(
                'purchase_order',
                __('There is already an open purchase order (:reference). Add the products to it, or submit or cancel it before creating a new one.', ['reference' => $openPurchaseOrder->reference])
            );
        }

        if ($this->parent instanceof OrgPartner) {
            return;
        }

        if ($this->parent->orgSupplierProducts()->where('is_available', true)->doesntExist()) {
            $message = $this->parent instanceof OrgAgent
                ? __("Agent don't have any product")
                : __("Supplier don't have any product");
            $validator->errors()->add('purchase_order', $message);
        }
    }

    public function action(OrgAgent|OrgSupplier|OrgPartner $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): PurchaseOrder
    {
        if (!$audit) {
            PurchaseOrder::disableAuditing();
        }
        $this->asAction       = true;
        $this->parent         = $parent;
        $this->strict         = $strict;
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
