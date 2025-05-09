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
                'mit_saved_card' => MitSavedCardResource::make($mitSavedCard)
            ]
        );
    }


}
