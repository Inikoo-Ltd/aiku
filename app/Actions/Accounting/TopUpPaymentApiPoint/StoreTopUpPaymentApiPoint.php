<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 18:11:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint;

use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreTopUpPaymentApiPoint extends RetinaAction
{
    use AsObject;

    public function handle(Customer $customer, array $modelData): TopUpPaymentApiPoint
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'ulid', Str::ulid());


        $paymentMethodsData  = [];
        $paymentAccountShops = $this->shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->where('show_in_checkout', true)
            ->orderby('checkout_display_position')
            ->get();
        /** @var PaymentAccountShop $paymentAccountShop */
        foreach ($paymentAccountShops as $paymentAccountShop) {
            $paymentMethodsData[$paymentAccountShop->type->value] = $paymentAccountShop->id;
        }

        $data                            = Arr::get($modelData, 'data', []);
        $data['payment_account_shop_id'] = $paymentMethodsData;
        data_set($modelData, 'data', $data);

        return $customer->topUpPaymentApiPoint()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'data'   => ['sometimes', 'array'],
        ];
    }


    public function asController(ActionRequest $request): TopUpPaymentApiPoint
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }


    public function htmlResponse(TopUpPaymentApiPoint $topUpPaymentApiPoint): RedirectResponse
    {
        return Redirect::route('retina.top_up.checkout', [
            'topUpPaymentApiPoint' => $topUpPaymentApiPoint->id
        ]);
    }


}
