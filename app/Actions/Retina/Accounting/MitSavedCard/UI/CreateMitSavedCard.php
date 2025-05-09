<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 19:46:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\MitSavedCard\UI;

use App\Actions\Accounting\MitSavedCard\StoreMitSavedCard;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\MitSavedCard;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use Checkout\Payments\ThreeDsRequest;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class CreateMitSavedCard extends RetinaAction
{
    use WithCheckoutCom;

    public function handle(): array
    {
        $paymentAccountShop = $this->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)->first();

        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();
        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);


        $mitSavedCard = StoreMitSavedCard::run(
            $this->customer,
            [
                'payment_account_shop_id' => $paymentAccountShop->id,
            ]
        );

        $paymentSessionClient = $checkoutApi->getPaymentSessionsClient();


        $paymentSessionRequest               = new PaymentSessionsRequest();
        $paymentSessionRequest->amount       = 0;
        $paymentSessionRequest->payment_type = 'Unscheduled';

        $paymentSessionRequest->currency = $this->shop->currency->code;

        $paymentSessionRequest->three_ds                      = new ThreeDsRequest();
        $paymentSessionRequest->three_ds->enabled             = true;
        $paymentSessionRequest->three_ds->challenge_indicator = 'challenge_requested_mandate';

        $channelID                                    = $paymentAccountShop->getCheckoutComChannel();
        $paymentSessionRequest->processing_channel_id = $channelID;

        $paymentSessionRequest->success_url = $this->getSuccessUrl($mitSavedCard);
        $paymentSessionRequest->failure_url = $this->getFailureUrl($mitSavedCard);

        $billingAddress = $this->customer->address;

        $paymentSessionRequest = $this->setBillingInformation($paymentSessionRequest, $billingAddress);


        try {
            $paymentSession = $paymentSessionClient->createPaymentSessions($paymentSessionRequest);
        } catch (\Exception $e) {
            $paymentSession = [
                'error' => $e->getMessage(),
            ];
            Sentry::captureException($e);
        }

        return [
            'label'       => __('Online payments'),
            'key'         => 'credit_card',
            'public_key'  => $publicKey,
            'environment' => app()->environment('production') ? 'production' : 'sandbox',
            'locale'      => 'en',
            'icon'        => 'fal fa-credit-card-front',
            'data'        => $paymentSession
        ];
    }


    private function getSuccessUrl(MitSavedCard $mitSavedCard): string
    {
        return route('retina.webhooks.checkout_com.mit_saved_card_success', $mitSavedCard->ulid);
    }

    private function getFailureUrl(MitSavedCard $mitSavedCard): string
    {
        return route('retina.webhooks.checkout_com.mit_saved_card_failure', $mitSavedCard->ulid);
    }


    public function asController(ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle();
    }

    public function htmlResponse(array $checkoutComData): Response
    {
        $title = __('Saved Credit Card');

        return Inertia::render('Dropshipping/CreateMitSavedCard', [
            'title'             => $title,
            'breadcrumbs'       => $this->getBreadcrumbs(),
            'pageHead'          => [

                'title' => $title,
                'icon'  => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => $title
                ],
            ],
            'checkout_com_data' => $checkoutComData

        ]);
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.saved-credit-card.show'
                            ],
                            'label' => __('Saved Credit Card'),
                        ]
                    ]
                ]
            );
    }
}
