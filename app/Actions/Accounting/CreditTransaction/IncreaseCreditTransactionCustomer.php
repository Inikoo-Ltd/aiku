<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 28 Aug 2025 09:27:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\CreditTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class IncreaseCreditTransactionCustomer extends OrgAction
{
    use WithCRMEditAuthorisation;
    use WithModelAddressActions;
    use WithNoStrictRules;
    use WithCreditTransactionRules;


    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): CreditTransaction
    {
        return StoreCreditTransaction::make()->action($customer, $modelData);
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        if (blank($this->get('type'))) {
            $type = match ($request->input('reason')) {
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
