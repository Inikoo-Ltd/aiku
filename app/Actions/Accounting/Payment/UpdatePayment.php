<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 14:31:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydrateCustomers;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdatePayment extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $payment = $request->route()->parameter('payment');
        if ($payment instanceof Payment && !$payment->paymentAccount?->type?->isManuallySettled()) {
            return false;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function handle(Payment $payment, array $modelData): Payment
    {
        $payment = $this->update($payment, $modelData, ['data']);
        $changes = Arr::except($payment->getChanges(), ['updated_at', 'last_fetched_at']);


        if (Arr::has($changes, 'status')) {
            PaymentAccountHydrateCustomers::dispatch($payment->paymentAccount)->delay($this->hydratorsDelay);
        }

        if (Arr::hasAny($changes, ['amount', 'status', 'state'])) {
            foreach ($payment->invoices as $invoice) {
                UpdateInvoicePaymentState::run($invoice);
            }
            foreach ($payment->orders as $order) {
                UpdateOrderPaymentsStatus::run($order);
            }
        }

        return $payment;
    }

    public function rules(): array
    {
        $rules = [
            'reference'  => ['sometimes', 'nullable', 'max:255', 'string'],
            'amount'     => ['sometimes', 'decimal:0,2'],
            'date'       => ['sometimes', 'date'],
            'org_amount' => ['sometimes', 'numeric'],
            'grp_amount' => ['sometimes', 'numeric'],
        ];

        if (!$this->strict) {
            $rules                = $this->noStrictUpdateRules($rules);
            $rules['shop_id']     = ['sometimes', 'required', 'exists:shops,id'];
            $rules['customer_id'] = ['sometimes', 'required', 'exists:customers,id'];
        }

        return $rules;
    }

    public function action(Payment $payment, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Payment
    {
        $this->strict = $strict;
        if (!$audit) {
            Payment::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($payment->shop, $modelData);

        return $this->handle($payment, $this->validatedData);
    }


    public function asController(Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisationFromShop($payment->shop, $request);

        return $this->handle($payment, $this->validatedData);
    }

    public function jsonResponse(Payment $payment): PaymentsResource
    {
        return new PaymentsResource($payment);
    }
}
