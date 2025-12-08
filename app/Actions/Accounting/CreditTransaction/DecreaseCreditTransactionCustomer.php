<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 28 Aug 2025 09:27:37 Central Indonesia Time, Sanur, Bali, Indonesia
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

class DecreaseCreditTransactionCustomer extends OrgAction
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


            $type = match ($this->get('reason')) {
                CreditTransactionReasonEnum::MONEY_BACK->value => CreditTransactionTypeEnum::MONEY_BACK,
                CreditTransactionReasonEnum::OTHER->value => CreditTransactionTypeEnum::REMOVE_FUNDS_OTHER,
                CreditTransactionReasonEnum::TRANSFER->value => CreditTransactionTypeEnum::TRANSFER_OUT,
            };

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
