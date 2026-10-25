<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement;
use App\Actions\SupplyChain\PackingSpec\GetPurchaseOrderPackingSpecGaps;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\SendAgentDraftsToAgent;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrderStateToSubmitted extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use AsAction;
    use HasPurchaseOrderHydrators;

    private PurchaseOrder $purchaseOrder;

    public function afterValidator(Validator $validator): void
    {
        if ($this->purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
            $validator->errors()->add('state', __('Purchase order can only be submitted if it is in process'));
        }

        if ($this->purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::IN_PROCESS)
            ->doesntExist()) {
            $validator->errors()->add('transactions', __('Purchase order must have at least one item to be submitted'));
        }

        if (!$this->asAction && $waiting = SendAgentDraftsToAgent::organisationsNotReady($this->purchaseOrder)) {
            $validator->errors()->add('purchase_order', __(':organisations are also ordering from this supplier and have not said they are ready. Submit it from POs to submit.', ['organisations' => implode(', ', $waiting)]));
        }

        if (!$this->asAction && !$this->get('critical_notes_acknowledged') && GetPurchaseOrderAgreement::make()->hasCriticalNotes($this->purchaseOrder)) {
            $validator->errors()->add('critical_notes', __('This order has critical notes. Read them and tick that you have before submitting.'));
        }

        if (!$this->asAction && CheckAgainstAgreedTerms::hasUnaccepted(CheckAgainstAgreedTerms::make()->purchaseOrder($this->purchaseOrder))) {
            $validator->errors()->add('agreed_terms', __('This order differs from what was agreed with the supplier. Accept each difference with a reason before submitting.'));
        }

        $packingSpecGaps = GetPurchaseOrderPackingSpecGaps::run($this->purchaseOrder);
        if ($packingSpecGaps && $packingSpecGaps['blocking']) {
            $validator->errors()->add('packing_specs', GetPurchaseOrderPackingSpecGaps::make()->blockingMessage($packingSpecGaps));
        }

        if (SendPartnerPurchaseOrderToSeller::appliesTo($this->purchaseOrder)) {
            foreach (SendPartnerPurchaseOrderToSeller::make()->problems($this->purchaseOrder) as $problem) {
                $validator->errors()->add('purchase_order', $problem);
            }
        }
    }

    public function handle(PurchaseOrder $purchaseOrder, ?string $sendVia = null): PurchaseOrder
    {
        $purchaseOrder = DB::transaction(fn () => $this->submit($purchaseOrder, $sendVia));

        if ($agentOrganisation = $purchaseOrder->agent?->organisation) {
            BroadcastAgentIncomingPurchaseOrdersChanged::dispatch($agentOrganisation->id, [
                'purchase_order_id' => $purchaseOrder->id,
                'reference'         => $purchaseOrder->reference,
                'client'            => $purchaseOrder->organisation->name,
                'amount'            => (float) $purchaseOrder->cost_total,
                'currency'          => $purchaseOrder->currency->code,
                'url'               => route('grp.org.agent.purchase_orders.show', [$agentOrganisation->slug, $purchaseOrder->slug]),
            ]);
        }

        if (SendPartnerPurchaseOrderToSeller::appliesTo($purchaseOrder)) {
            SendPartnerPurchaseOrderToSeller::dispatch($purchaseOrder);
        }

        return $purchaseOrder;
    }

    private function submit(PurchaseOrder $purchaseOrder, ?string $sendVia): PurchaseOrder
    {
        $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::IN_PROCESS)
            ->update([
                'state'                        => PurchaseOrderTransactionStateEnum::SUBMITTED,
                'historic_supplier_product_id' => DB::raw('coalesce((select current_historic_supplier_product_id from supplier_products where supplier_products.id = purchase_order_transactions.supplier_product_id), historic_supplier_product_id)'),
            ]);

        $updateData = [
            'state'        => PurchaseOrderStateEnum::SUBMITTED,
            'submitted_at' => now(),
        ];

        if ($purchaseOrder->estimated_delivery_days === null) {
            $deliveryDays = $purchaseOrder->purchaseOrderTransactions()
                ->where('purchase_order_transactions.state', PurchaseOrderTransactionStateEnum::SUBMITTED)
                ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
                ->selectRaw("max(coalesce(supplier_products.measured_lead_time_days, supplier_products.estimated_lead_time_days, case when supplier_products.data->>'delivery_time' ~ '^[0-9]+$' then (supplier_products.data->>'delivery_time')::int end)) as delivery_days")
                ->value('delivery_days');

            if ($deliveryDays !== null) {
                $updateData['estimated_delivery_days'] = $deliveryDays;
                $updateData['estimated_received_at']   = $updateData['submitted_at']->clone()->addDays($deliveryDays);
            }
        }

        $purchaseOrder = $this->update($purchaseOrder, $updateData);

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder)->afterCommit();

        $this->purchaseOrderHydrate($purchaseOrder);

        if (SendPartnerPurchaseOrderToSeller::appliesTo($purchaseOrder)) {
            return $purchaseOrder;
        }

        if ($sendVia && in_array($sendVia, array_column(SendPurchaseOrderToSupplier::channels($purchaseOrder), 'channel'), true)) {
            SendPurchaseOrderToSupplier::dispatch($purchaseOrder, $sendVia);
        }

        return $purchaseOrder;
    }

    public function rules(): array
    {
        return [
            'send_via' => ['sometimes', 'nullable', 'string', 'in:email,whatsapp'],
            'critical_notes_acknowledged' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, Arr::get($this->validatedData, 'send_via'));
    }

    public function action(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
