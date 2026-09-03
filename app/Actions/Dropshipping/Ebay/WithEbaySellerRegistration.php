<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 17:26:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;

/**
 * eBay refuses to list anything until the seller has finished registering, and it only says so at
 * the moment a listing is published. With draft uploads switched on that moment arrives long after
 * the upload appeared to succeed, so the seller is told nothing is wrong until they press publish.
 *
 * The selling privileges answer the same question up front, so it is asked once while the channel
 * is checked and kept on the eBay user for the dashboard to read.
 */
trait WithEbaySellerRegistration
{
    protected function refreshEbaySellerRegistration(EbayUser $ebayUser): void
    {
        $privileges = $ebayUser->getPrivileges();

        // An unreadable answer is not evidence of a problem, so the last known state stands.
        if (!is_array($privileges) || Arr::has($privileges, 'error')) {
            return;
        }

        $ebayUser->updateQuietly([
            'data' => array_merge((array)$ebayUser->data, [
                'seller_registration_completed' => (bool)Arr::get($privileges, 'sellerRegistrationCompleted', true),
            ]),
        ]);
    }
}
