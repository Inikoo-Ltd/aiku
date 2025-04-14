<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class UpdateBalanceCustomer extends OrgAction
{
    use AsCommand;
    use WithModelAddressActions;
    use WithNoStrictRules;


    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): CreditTransaction
    {

        data_set($modelData, 'date', now());

        $modelData['type'] = CreditTransactionTypeEnum::from($modelData['type']);
        $modelData['amount'] = match ($modelData['type']) {
            CreditTransactionTypeEnum::PAY_RETURN,
            CreditTransactionTypeEnum::COMPENSATION,
            CreditTransactionTypeEnum::TRANSFER_IN,
            CreditTransactionTypeEnum::ADD_FUNDS_OTHER => abs($modelData['amount']),
            CreditTransactionTypeEnum::MONEY_BACK,
            CreditTransactionTypeEnum::TRANSFER_OUT,
            CreditTransactionTypeEnum::REMOVE_FUNDS_OTHER => abs($modelData['amount']) * -1,
        };

        $creditTransaction = StoreCreditTransaction::make()->action($customer, $modelData);

        return $creditTransaction;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'decimal:0,2'],
            'type' => ['required',  Rule::in([
                CreditTransactionTypeEnum::PAY_RETURN->value,
                CreditTransactionTypeEnum::COMPENSATION->value,
                CreditTransactionTypeEnum::TRANSFER_IN->value,
                CreditTransactionTypeEnum::ADD_FUNDS_OTHER->value,
                CreditTransactionTypeEnum::MONEY_BACK->value,
                CreditTransactionTypeEnum::TRANSFER_OUT->value,
                CreditTransactionTypeEnum::REMOVE_FUNDS_OTHER->value,
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): CreditTransaction
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): CreditTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
