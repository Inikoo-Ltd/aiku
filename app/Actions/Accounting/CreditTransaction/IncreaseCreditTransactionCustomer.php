<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 28 Aug 2025 09:27:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\CreditTransaction;

use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Invoice\StoreStandaloneCreditNote;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCustomerBalanceAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class IncreaseCreditTransactionCustomer extends OrgAction
{
    use WithCustomerBalanceAuthorisation;
    use WithModelAddressActions;
    use WithNoStrictRules;
    use WithCreditTransactionRules {
        rules as creditTransactionRules;
    }


    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): CreditTransaction
    {
        $issueCreditNote = Arr::pull($modelData, 'issue_credit_note', false);
        $requestedBy     = Arr::pull($modelData, 'requested_by');

        if ($requestedBy) {
            data_set($modelData, 'data.requested_by', $requestedBy);
        }
        if ($appliedBy = request()->user()?->contact_name) {
            data_set($modelData, 'data.applied_by', $appliedBy);
        }

        if ($issueCreditNote) {
            $creditNote = StoreStandaloneCreditNote::make()->action($customer, [
                'amount' => Arr::get($modelData, 'amount'),
                'reason' => CreditTransactionReasonEnum::getStaticLabel(Arr::get($modelData, 'reason')),
            ], $this->hydratorsDelay);

            $paymentAccount = $customer->shop->paymentAccountShops()
                ->where('type', PaymentAccountTypeEnum::ACCOUNT)
                ->firstOrFail()->paymentAccount;

            $payment = StorePayment::make()->action($customer, $paymentAccount, [
                'amount' => $creditNote->total_amount,
                'status' => PaymentStatusEnum::SUCCESS->value,
                'state'  => PaymentStateEnum::COMPLETED->value,
                'type'   => PaymentTypeEnum::REFUND,
            ], $this->hydratorsDelay);

            AttachPaymentToInvoice::make()->action($creditNote, $payment, []);

            data_set($modelData, 'payment_id', $payment->id);
        }

        return StoreCreditTransaction::make()->action($customer, $modelData, $this->hydratorsDelay, $this->strict);
    }

    public function rules(): array
    {
        return array_merge($this->creditTransactionRules(), [
            'issue_credit_note' => ['sometimes', 'boolean'],
            'requested_by'      => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        if (blank($this->get('type'))) {
            $type = match ($this->get('reason')) {
                CreditTransactionReasonEnum::PAY_FOR_SHIPPING->value,
                CreditTransactionReasonEnum::PAY_FOR_PRODUCT->value,
                CreditTransactionReasonEnum::COMPENSATE_CUSTOMER->value => CreditTransactionTypeEnum::COMPENSATION,
                CreditTransactionReasonEnum::OTHER->value, CreditTransactionReasonEnum::TRANSFER->value => CreditTransactionTypeEnum::ADD_FUNDS_OTHER
            };

            if (in_array($this->get('reason'), [CreditTransactionReasonEnum::PAY_FOR_SHIPPING->value, CreditTransactionReasonEnum::PAY_FOR_PRODUCT->value]) && !blank($this->get('notes'))) {
                $this->set('notes', CreditTransactionReasonEnum::getStaticLabel($this->get('reason')) . '. '. $this->get('notes'));
            }
            $this->set('type', $type->value);
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): void
    {
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true): CreditTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
