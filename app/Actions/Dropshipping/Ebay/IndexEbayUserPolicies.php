<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use App\Models\Helpers\TaxCategory;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Spatie\LaravelOptions\Options;

class IndexEbayUserPolicies extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithEbayApiRequest;

    public function handle(EbayUser $ebayUser): array
    {
        $fulfillmentPolicies = $ebayUser->getFulfilmentPolicies();
        $paymentPolicies = $ebayUser->getPaymentPolicies();
        $returnPolicies = $ebayUser->getReturnPolicies();
        $shippingServices = $ebayUser->getServicesForOptions();

        return [
            'fulfillment_policies' => $fulfillmentPolicies,
            'payment_policies' => $paymentPolicies,
            'return_policies' => $returnPolicies,
            'shipping_services' => collect($shippingServices)->map(function ($shippingService, $key) {
                return [
                    'name' =>  $shippingService,
                    'value' => $key
                ];
            })->values(),
            'tax_categories' => Options::forModels(TaxCategory::class)
        ];
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($ebayUser);
    }
}
