<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithRunInvoiceHydrators;
use App\Actions\Comms\Email\SendInvoiceToFulfilmentCustomerEmail;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class FinaliseRefund extends OrgAction
{
    use WithActionUpdate;
    use WithRunInvoiceHydrators;

    public function handle(Invoice $refund): Invoice
    {
        $orgExchange = GetCurrencyExchange::run($refund->shop->currency, $refund->organisation->currency);
        $grpExchange = GetCurrencyExchange::run($refund->shop->currency, $refund->group->currency);

        foreach ($refund->invoiceTransactions as $invoiceTransaction) {
            $invoiceTransaction->update(
                [
                    'in_process'     => false,
                    'org_exchange'   => $orgExchange,
                    'grp_exchange'   => $grpExchange,
                    'date'           => now(),
                    'org_net_amount' => $invoiceTransaction->net_amount * $orgExchange,
                    'grp_net_amount' => $invoiceTransaction->net_amount * $grpExchange,
                ]
            );
        }


        $refund->update(
            [
                'in_process'     => false,
                'org_exchange'   => $orgExchange,
                'grp_exchange'   => $grpExchange,
                'date'           => now(),
                'org_net_amount' => $refund->net_amount * $orgExchange,
                'grp_net_amount' => $refund->net_amount * $grpExchange,
            ]
        );


        $this->runInvoiceHydrators($refund);
        if ($refund->shop->type == 'fulfilment') {
            SendInvoiceToFulfilmentCustomerEmail::dispatch($refund);
        }

        return $refund;
    }

    public function htmlResponse(): RedirectResponse
    {
        $previousUrl   = url()->previous();
        $previousRoute = app('router')->getRoutes()->match(app('request')->create($previousUrl));

        $previousRouteName              = $previousRoute->getName();
        $previousRouteParameters        = $previousRoute->parameters();
        $previousRouteParameters['tab'] = 'items';

        return Redirect::route($previousRouteName, $previousRouteParameters);
    }

    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund);
    }

    public function action(Invoice $refund, array $modelData): Invoice
    {
        $this->initialisationFromShop($refund->shop, $modelData);

        return $this->handle($refund);
    }
}
