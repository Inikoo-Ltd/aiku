<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 20:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingOrShopEdit;
use App\Actions\OrgAction;
use App\Models\Accounting\BankTransaction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the bank review queue: a person names the customer a bank payment that pays no order came from, and the money
 * is added to that customer's credit.
 */
class PayBankTransactionToCustomerCredit extends OrgAction
{
    use AuthorizesAccountingOrShopEdit;

    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(BankTransaction $bankTransaction, array $modelData): BankTransaction
    {
        $customer = Customer::where('organisation_id', $bankTransaction->organisation_id)
            ->when(isset($this->shop), fn ($query) => $query->where('shop_id', $this->shop->id))
            ->find($modelData['customer_id']);
        if (!$customer) {
            throw ValidationException::withMessages(['customer' => isset($this->shop) ? __('No such customer in this shop') : __('No such customer in this organisation')]);
        }

        DB::transaction(fn () => $this->lockLineOpenToShop($bankTransaction));
        PayMatchedBankTransactions::make()->payToCustomerCredit(
            $bankTransaction,
            $customer,
            $this->user?->contact_name ?? $this->user?->username ?? 'staff'
        );

        $bankTransaction->refresh()->recordDecision('added_to_customer_credit', array_filter([
            'customer'  => $customer->reference,
            'amount'    => $bankTransaction->amount,
            'currency'  => $bankTransaction->currency->code,
            'from_shop' => isset($this->shop) ? $this->shop->code : null,
        ]));

        return $bankTransaction;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->user = $request->user();
        $this->initialisation($organisation, $request);
        abort_unless($bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inShop(Organisation $organisation, Shop $shop, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->user = $request->user();
        $this->initialisationFromShop($shop, $request);
        abort_unless($shop->organisation_id === $organisation->id && $bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
