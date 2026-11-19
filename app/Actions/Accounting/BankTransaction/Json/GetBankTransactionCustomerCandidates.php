<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 20:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\Json;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Models\Accounting\BankTransaction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;

/**
 * Customers whose credit a bank line could go to, for the Customer credit dialog of the bank review: customers of
 * this organisation in shops selling in the line's currency, found by reference or name; an empty search looks for
 * the first word of the payer's name.
 */
class GetBankTransactionCustomerCandidates extends OrgAction
{
    use WithAccountingPagesAuthorisation;

    /**
     * @return list<array{id: int, reference: string|null, name: string|null, shop: string, balance: float}>
     */
    public function handle(BankTransaction $bankTransaction, string $search, ?Shop $shop = null, bool $anyCurrency = false): array
    {
        $search = trim($search) !== '' ? trim($search) : (MatchBankTransactions::make()->nameWords($bankTransaction->counterparty_name)[0] ?? '');
        if (mb_strlen($search) < 2) {
            return [];
        }

        return Customer::query()
            ->where('customers.organisation_id', $bankTransaction->organisation_id)
            ->join('shops', 'shops.id', 'customers.shop_id')
            ->when($shop, fn (Builder $query) => $query->where('customers.shop_id', $shop->id))
            ->when(!$anyCurrency, fn (Builder $query) => $query->where('shops.currency_id', $bankTransaction->currency_id))
            ->where(function (Builder $query) use ($search) {
                $query->whereStartWith('customers.reference', $search)
                    ->orWhere(fn (Builder $query) => $query->whereAnyWordStartWith('customers.name', $search));
            })
            ->orderByRaw('customers.last_submitted_order_at desc nulls last')
            ->limit(15)
            ->get(['customers.id', 'customers.reference', 'customers.name', 'customers.balance', 'shops.code as shop_code'])
            ->map(fn (Customer $customer) => [
                'id'        => $customer->id,
                'reference' => $customer->reference,
                'name'      => $customer->name,
                'shop'      => $customer->shop_code,
                'balance'   => (float)$customer->balance,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function asController(Organisation $organisation, BankTransaction $bankTransaction, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        abort_unless($bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, (string)$request->query('search', ''));
    }

    /**
     * Customers of one shop, for its Bank payments page; with any_currency, also when the payment came in another
     * currency, to say whose it is.
     *
     * @return list<array<string, mixed>>
     */
    public function inShop(Organisation $organisation, Shop $shop, BankTransaction $bankTransaction, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);
        abort_unless($shop->organisation_id === $organisation->id && $bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, (string)$request->query('search', ''), $shop, $request->boolean('any_currency'));
    }
}
