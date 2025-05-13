<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 16:17:16 Malaysia Time, Kuala Lumpur, Malaysia
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
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CheckoutComMitSavedCardFailure extends RetinaWebhookAction
{
    use WithCheckoutCom;
    use withCheckoutComMitSavedCardWebhook;

    public function handle(MitSavedCard $mitSavedCard, array $modelData): ?MitSavedCard
    {
        $payment = $this->getCheckOutPayment(
            $mitSavedCard->paymentAccountShop,
            $modelData['cko-payment-id']
        );
        if (Arr::get($payment, 'error')) {
            return $this->processError($mitSavedCard, $payment);
        }


        return UpdateMitSavedCard::make()->asAction(
            $mitSavedCard,
            [
                'state'          => MitSavedCardStateEnum::FAILURE,
                'processed_at'   => now(),
                'failure_status' => Arr::get($payment, 'status'),
                'data'           => [
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

        if ($mitSavedCard->state == MitSavedCardStateEnum::ERROR) {
            return $this->errorRedirect($mitSavedCard);
        }

        return Redirect::route('retina.dropshipping.mit_saved_cards.create')->with(
            'notification',
            [
                'status'         => 'failure',
                'title'          => $this->getFailureTitle($mitSavedCard->failure_status),
                'message'          => $this->getFailureMessage($mitSavedCard->failure_status),
                'mit_saved_card' => MitSavedCardResource::make($mitSavedCard)
            ]
        );
    }

    protected function getFailureTitle($status): string
    {
        $title = __('Error');
        if ($status == 'Declined') {
            $title = __('Declined');
        } elseif ($status == 'Expired') {
            $title = __('Expired');
        } elseif ($status == 'Canceled') {
            $title = __('Canceled');
        }

        return $title;
    }

    protected function getFailureMessage($status): string
    {
        $message = __('There was an error processing your card. Please try again or use a different payment method.');

        if ($status == 'Declined') {
            $message = __('Your card was declined by the issuing bank. Please try another payment method or contact your bank for more information.');
        } elseif ($status == 'Expired') {
            $message = __('Your payment session has expired. Please try the transaction again.');
        } elseif ($status == 'Canceled') {
            $message = __('This payment was canceled. Please try again when you are ready to complete your payment.');
        } elseif ($status == 'Failed') {
            $message = __('The payment processing failed. Please verify your card details and try again.');
        }

        return $message;
    }


}
