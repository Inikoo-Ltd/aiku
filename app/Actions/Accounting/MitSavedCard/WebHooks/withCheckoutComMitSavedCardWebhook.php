<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 22:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\MitSavedCard\WebHooks;

use App\Actions\Accounting\MitSavedCard\UpdateMitSavedCard;
use App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum;
use App\Http\Resources\Accounting\MitSavedCardResource;
use App\Models\Accounting\MitSavedCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;

trait withCheckoutComMitSavedCardWebhook
{
    private function processError(MitSavedCard $mitSavedCard, array $payment): MitSavedCard
    {
        return UpdateMitSavedCard::make()->asAction(
            $mitSavedCard,
            [
                'state'        => MitSavedCardStateEnum::ERROR,
                'processed_at' => now(),
                'data'         => [
                    'error' => Arr::except($payment, ['error'])
                ]

            ]
        );
    }

    private function errorRedirect(MitSavedCard $mitSavedCard): RedirectResponse
    {
        return Redirect::route('retina.dropshipping.mit_saved_cards.create')->with(
            'notification',
            [
                'status'         => 'error',
                'mit_saved_card' => MitSavedCardResource::make($mitSavedCard)
            ]
        );
    }
}
