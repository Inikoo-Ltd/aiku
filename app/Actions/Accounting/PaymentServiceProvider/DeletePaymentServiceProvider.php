<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider;

use App\Actions\GrpAction;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePaymentServiceProvider extends GrpAction
{
    use WithAttributes;

    public function handle(PaymentServiceProvider $paymentServiceProvider): PaymentServiceProvider
    {
        $paymentServiceProvider->payments()->delete();
        $paymentServiceProvider->accounts()->delete();
        $paymentServiceProvider->delete();

        return $paymentServiceProvider;
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("accounting.payment-service-providers.edit");
    }

    public function asController(PaymentServiceProvider $paymentServiceProvider, ActionRequest $request): PaymentServiceProvider
    {
        $this->initialisation($paymentServiceProvider->group, $request);

        return $this->handle($paymentServiceProvider);
    }

    public function action(PaymentServiceProvider $paymentServiceProvider): PaymentServiceProvider
    {
        $this->asAction = true;
        $this->initialisation($paymentServiceProvider->group, []);

        return $this->handle($paymentServiceProvider);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.accounting.org_payment_service_providers.index');
    }

}
