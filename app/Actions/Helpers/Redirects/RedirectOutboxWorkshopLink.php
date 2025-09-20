<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Sept 2025 23:31:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Comms\Outbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOutboxWorkshopLink extends OrgAction
{
    public function handle(Outbox $outbox): ?RedirectResponse
    {
        if ($outbox->shop->type != ShopTypeEnum::FULFILMENT) {
            return $this->redirectOutboxToShop($outbox);
        } else {
            return $this->redirectOutboxToFulfilment($outbox);
        }
    }

    protected function redirectOutboxToShop(Outbox $outbox): ?RedirectResponse
    {
        $organisation = $outbox->organisation;
        $shop         = $outbox->shop;
        $route        = [
            'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.workshop',
            'parameters' => [
                'organisation' => $organisation->slug,
                'shop'         => $shop->slug,
                'outbox'       => $outbox->slug
            ]
        ];

        return Redirect::route($route['name'], $route['parameters']);
    }

    protected function redirectOutboxToFulfilment(Outbox $outbox): RedirectResponse
    {
        $organisation = $outbox->organisation;
        $shop         = $outbox->shop;
        $route        = [
            'name'       => 'grp.org.fulfilments.show.operations.comms.outboxes.workshop',
            'parameters' => [
                'organisation' => $organisation->slug,
                'shop'         => $shop->fulfilment->slug,
                'outbox'       => $outbox->slug
            ]
        ];

        return Redirect::route($route['name'], $route['parameters']);
    }

    public function asController(Outbox $outbox, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($outbox->shop, $request);

        return $this->handle($outbox);
    }

}
