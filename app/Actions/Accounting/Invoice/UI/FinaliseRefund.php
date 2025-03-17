<?php
/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/


namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Enums\UI\Accounting\InvoicesTabsEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class FinaliseRefund extends OrgAction
{
    public function handle(Invoice $refund): Invoice
    {

        dd('hello');

        return $refund;
    }

    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund);
    }
}
