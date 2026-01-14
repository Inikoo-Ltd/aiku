<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-14h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Accounting\Invoice\UI\CreateRefund;
use App\Actions\Accounting\Invoice\UI\FinaliseRefund;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Laravel\Octane\Facades\Octane;
use Lorisleiva\Actions\ActionRequest;

class RefundTaxTransactions extends OrgAction
{
    use WithActionUpdate;
    private array $referralRoute = [
        'name' => 'dashboard',
        'parameters' => []
    ];
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice): Invoice
    {
        $originalInvoice = $invoice;
        $refund = CreateRefund::run($invoice);

        $transactions = $originalInvoice->invoiceTransactions->where('net_amount', '>', 0);
        $tasks        = [];

        $refund = $this->update($refund, [
            'is_tax_only' => true
        ]);

        foreach ($transactions->chunk(100) as $chunkedTransactions) {
            foreach ($chunkedTransactions as $transaction) {

                $taxAmount = $transaction->taxCategory->rate * $transaction->net_amount;

                $tasks[] = fn () => StoreRefundInvoiceTransaction::run($refund, $transaction, [
                    'net_amount'  => 0,
                    'refund_all'  => false,
                    'is_tax_only' => true,
                    'quantity'    => 1,
                    'tax_amount'  => $taxAmount,
                    'amount_total'  => $taxAmount
                ]);
            }
            Octane::concurrently($tasks);
            $tasks = [];
        }

        CalculateInvoiceTotals::run($refund);
        FinaliseRefund::run($refund);

        return $refund;
    }

    public function htmlResponse(Invoice $refund, ActionRequest $request): RedirectResponse
    {
        return Redirect::route(
            $this->referralRoute['name'].'.refunds.show',
            array_merge($this->referralRoute['parameters'], [$refund->slug])
        );
    }

    public function rules(): array
    {
        return [
            'referral_route' => ['sometimes','array'],
            'referral_route.name' => ['required','string'],
            'referral_route.parameters' => ['required','array'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($invoice->shop, $request);

        if (Arr::has($this->validatedData, 'referral_route')) {
            $this->referralRoute = $this->validatedData['referral_route'];
        }

        return $this->handle($invoice);
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $refund): Invoice
    {
        $this->initialisationFromShop($refund->shop, []);

        return $this->handle($refund);
    }

}
