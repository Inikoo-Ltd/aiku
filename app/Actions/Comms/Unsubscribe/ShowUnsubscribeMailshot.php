<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Nov 2023 14:41:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\DispatchedEmail;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class ShowUnsubscribeMailshot
{
    use WithActionUpdate;

    public function handle(DispatchedEmail $dispatchedEmail): DispatchedEmail
    {
        return $dispatchedEmail;
    }

    public function asController(string $encryptedDispatchedEmailID): DispatchedEmail
    {
        try {
            $dispatchedEmailID = Crypt::decryptString($encryptedDispatchedEmailID);
        } catch (Exception) {
            abort(404);
        }
        $dispatchedEmail = DispatchedEmail::findOrFail($dispatchedEmailID);

        return $this->handle($dispatchedEmail);
    }

    public function htmlResponse(): Response
    {
        return Inertia::render('UnsubscribeMailshot', [
            'title'   => __('Unsubscribe'),
            'message' => [
                'confirmationTitle'  => __('Are you sure to unsubscribe?'),
                'successTitle'       => __('Unsubscription successful'),
                'successDescription' => __('You have been unsubscribed, sorry for any inconvenience caused.'),
                'button'             => __('Click here to unsubscribe'),
                'error'              => __('An error occurred while unsubscribing.'),
                'invalidParamsTitle' => __('Invalid Link'),
                'invalidParamsDesc'  => __('The unsubscribe link is invalid or has expired.'),
                'backHome'           => __('Back to Home'),
            ],
        ]);
    }
}
