<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 14:31:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Payment\Search\PaymentRecordSearch;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydrateCustomers;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdatePayment extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Payment $payment, array $modelData): Payment
    {
        $payment = $this->update($payment, $modelData, ['data']);
        $changes = Arr::except($payment->getChanges(), ['updated_at', 'last_fetched_at']);


        if (Arr::has($changes, 'status')) {
            PaymentAccountHydrateCustomers::dispatch($payment->paymentAccount)->delay($this->hydratorsDelay);
        }


        if (count($changes) > 0) {
            PaymentRecordSearch::dispatch($payment);
        }

        return $payment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference'  => ['sometimes', 'nullable', 'max:255', 'string'],
            'amount'     => ['sometimes', 'decimal:0,2'],
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
