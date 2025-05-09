<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\MitSavedCard\WebHooks;

use App\Actions\Accounting\MitSavedCard\UpdateMitSavedCard;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum;
use App\Http\Resources\Accounting\MitSavedCardResource;
use App\Models\Accounting\MitSavedCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;
use Checkout\CheckoutApiException;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CheckoutComMitSavedCardSuccess extends RetinaWebhookAction
{
    use WithCheckoutCom;


    public function handle(MitSavedCard $mitSavedCard, array $modelData): ?MitSavedCard
    {
        list($publicKey, $secretKey) = $mitSavedCard->paymentAccountShop->getCredentials();


        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);

        try {
            $payment = $checkoutApi->getPaymentsClient()->getPaymentDetails($modelData['cko-payment-id']);
        } catch (CheckoutApiException $e) {
            \Sentry\captureException($e);
            $error_details    = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
            print $http_status_code.' '.$error_details;

            return null;
        }


        return UpdateMitSavedCard::make()->asAction(
            $mitSavedCard,
            [
                'state'            => MitSavedCardStateEnum::SUCCESS,
                'token'            => Arr::get($payment, 'id'),
                'last_four_digits' => Arr::get($payment, 'source.last4'),
                'card_type'        => Arr::get($payment, 'source.scheme'),
                'expires_at'       => Carbon::parse(Arr::get($payment, 'source.expiry_year').'-'.Arr::get($payment, 'source.expiry_month').'-01'),
                'processed_at'     => now(),
                'data'             => [
                    'payment' => Arr::except($payment, ['http_metadata', '_links'])
                ]

            ]
        );
    }

    public function rules(): array
    {
        return [
            'cko-payment-session-id' => ['sometimes', 'string'],
            'cko-session-id'         => ['sometimes', 'string'],
            'cko-payment-id'         => ['sometimes', 'string'],
        ];
    }

    public function asController(MitSavedCard $mitSavedCard, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($request);
        $mitSavedCard = $this->handle($mitSavedCard, $this->validatedData);


        return Redirect::route('retina.dropshipping.saved_credit_card.dashboard')->with(
            'notification',
            [
                'status'         => 'success',
                'mit_saved_card' => MitSavedCardResource::make($mitSavedCard)
            ]
        );
    }


}
