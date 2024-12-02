<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Mar 2024 20:51:28 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;

trait HasRentalAgreement
{
    public function hasRentalAgreement(FulfilmentCustomer $fulfilmentCustomer): bool
    {
        return $fulfilmentCustomer->rentalAgreement
            && $fulfilmentCustomer->rentalAgreement->state === RentalAgreementStateEnum::ACTIVE;

    }
}
