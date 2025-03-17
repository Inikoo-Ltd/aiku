<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 18:04:45 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\Offer\Hydrators\OfferHydrateInvoices;
use App\Actions\Discounts\Offer\Hydrators\OfferHydrateOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Discounts\Offer;

class HydrateOffers
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:offers {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Offer::class;
    }

    public function handle(Offer $offer): void
    {
        OfferHydrateInvoices::run($offer);
        OfferHydrateOrders::run($offer);
    }

}
