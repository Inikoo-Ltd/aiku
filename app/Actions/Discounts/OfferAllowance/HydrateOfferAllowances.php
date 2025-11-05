<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 17:49:05 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferAllowance;

use App\Actions\Discounts\OfferAllowance\Hydrators\OfferAllowanceHydrateInvoices;
use App\Actions\Discounts\OfferAllowance\Hydrators\OfferAllowanceHydrateOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Discounts\OfferAllowance;

class HydrateOfferAllowances
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:offer_allowances {organisations?*} {--S|shop= shop slug} {--s|slug=}';


    public function __construct()
    {
        $this->model = OfferAllowance::class;
    }

    public function handle(OfferAllowance $offerAllowance): void
    {
        OfferAllowanceHydrateOrders::run($offerAllowance);
        OfferAllowanceHydrateInvoices::run($offerAllowance);
    }


}
