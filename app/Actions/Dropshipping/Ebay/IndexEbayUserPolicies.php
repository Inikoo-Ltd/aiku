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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

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

        return [
            'fulfillment_policies' => $fulfillmentPolicies,
            'payment_policies' => $paymentPolicies,
            'return_policies' => $returnPolicies,
        ];
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($ebayUser);
    }
}
