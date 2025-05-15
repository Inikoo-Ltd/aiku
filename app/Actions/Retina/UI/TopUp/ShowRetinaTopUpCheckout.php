<?php

/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\TopUp;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaAction;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use Checkout\Payments\ThreeDsRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class ShowRetinaTopUpCheckout extends RetinaAction
{
    use WithCheckoutCom;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint): array
    {
        $paymentAccountShopID = Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopID);

        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();

        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);

        $paymentSessionClient = $checkoutApi->getPaymentSessionsClient();


        $paymentSessionRequest            = new PaymentSessionsRequest();
        $paymentSessionRequest->amount    = (int)$topUpPaymentApiPoint->amount * 100;
        $paymentSessionRequest->currency  = $this->shop->currency->code;
        $paymentSessionRequest->reference = $topUpPaymentApiPoint->ulid;

        $paymentSessionRequest->three_ds          = new ThreeDsRequest();
        $paymentSessionRequest->three_ds->enabled = true;

        $channelID                                    = $paymentAccountShop->getCheckoutComChannel();
        $paymentSessionRequest->processing_channel_id = $channelID;
        $paymentSessionRequest->success_url           = $this->getSuccessUrl($topUpPaymentApiPoint);
        $paymentSessionRequest->failure_url           = $this->getFailureUrl($topUpPaymentApiPoint);



        $paymentSessionRequest = $this->setBillingInformation(
            $paymentSessionRequest,
            $this->customer->address
        );

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

    public function authorize(ActionRequest $request): bool
    {
        $topUpPaymentApiPoint = $request->route('topUpPaymentApiPoint');
        return $topUpPaymentApiPoint->customer_id == $this->customer->id;
    }




    public function asController(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request): array
    {
        $this->initialisation($request);

        if ($topUpPaymentApiPoint->state !== TopUpPaymentApiPointStateEnum::IN_PROCESS) {
            return [
                'error' => 'Top Up Payment expired'
            ];
        }

        return $this->handle($topUpPaymentApiPoint);
    }


    public function htmlResponse(array $checkoutComData, ActionRequest $request): \Illuminate\Http\Response|Response|\Illuminate\Http\RedirectResponse
    {

        if (Arr::has($checkoutComData, 'error')) {
            return Redirect::route('retina.top_up.dashboard')->with(
                'notification',
                [
                    'status'               => 'error',
                    'title'                => __('Failed to Top Up'),
                    'description'          => Arr::get($checkoutComData, 'error'),
                ]
            );
        }

        $title = __('Top Up Checkout');

        return Inertia::render(
            'Dropshipping/TopUp/TopUpCheckout',
            [
                'title'             => $title,
                'pageHead'          => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-money-bill-wave'],
                        'title' => $title
                    ],
                ],
                'checkout_com_data' => $checkoutComData
            ]
        );
    }

    private function getSuccessUrl(TopUpPaymentApiPoint $topUpPaymentApiPoint): string
    {
        return route('retina.webhooks.checkout_com.top_up_payment_success', $topUpPaymentApiPoint->ulid);
    }

    private function getFailureUrl(TopUpPaymentApiPoint $topUpPaymentApiPoint): string
    {
        return route('retina.webhooks.checkout_com.top_up_payment_failure', $topUpPaymentApiPoint->ulid);
    }


}
