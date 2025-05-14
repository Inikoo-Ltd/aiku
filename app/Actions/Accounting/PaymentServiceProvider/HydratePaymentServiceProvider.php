<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:23:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider;

use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydrateCustomers;
use App\Actions\HydrateModel;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePaymentAccounts;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePayments;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Support\Collection;

class HydratePaymentServiceProvider extends HydrateModel
{
    public string $commandSignature = 'hydrate:payment-service-providers {organisations?*} {--i|id=} ';

    public function handle(PaymentServiceProvider $paymentServiceProvider): void
    {
        PaymentServiceProviderHydratePaymentAccounts::run($paymentServiceProvider);
        PaymentServiceProviderHydratePayments::run($paymentServiceProvider);
        PaymentServiceProviderHydrateCustomers::run($paymentServiceProvider);
    }


    protected function getModel(string $slug): PaymentServiceProvider
    {
        return PaymentServiceProvider::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return PaymentServiceProvider::withTrashed()->get();
    }
}
