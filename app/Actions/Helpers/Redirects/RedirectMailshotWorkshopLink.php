<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 23 Jan 2026 09:39:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectMailshotWorkshopLink extends OrgAction
{
    public function handle(Mailshot $mailshot): ?RedirectResponse
    {
        if ($mailshot->type == MailshotTypeEnum::NEWSLETTER) {
            return $this->redirectToNewsletter($mailshot);
        } else {
            return $this->redirectToMarketing($mailshot);
        }
    }

    // TODOL fix the redirect later
    protected function redirectToNewsletter(Mailshot $mailshot): ?RedirectResponse
    {
        $organisation = $mailshot->organisation;
        $shop         = $mailshot->shop;
        $route        = [
            'name'       => 'grp.org.shops.show.marketing.mailshots.workshop',
            'parameters' => [
                'organisation'      => $organisation->slug,
                'shop'              => $shop->slug,
                'mailshot'          => $mailshot->slug
            ]
        ];

        return Redirect::route($route['name'], $route['parameters']);
    }

    protected function redirectToMarketing(Mailshot $mailshot): RedirectResponse
    {
        $organisation = $mailshot->organisation;
        $shop         = $mailshot->shop;
        $route        = [
            'name'       => 'grp.org.shops.show.marketing.mailshots.workshop',
            'parameters' => [
                'organisation'      => $organisation->slug,
                'shop'              => $shop->fulfilment->slug,
                'mailshot'          => $mailshot->slug
            ]
        ];

        return Redirect::route($route['name'], $route['parameters']);
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot);
    }

}
