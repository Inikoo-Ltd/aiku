<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:51:07 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CreateRefund extends OrgAction
{
    private array $referralRoute = [
        'name' => 'dashboard',
        'parameters' => []
    ];

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice): Invoice
    {
        return StoreRefund::make()->action($invoice, []);

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
}
