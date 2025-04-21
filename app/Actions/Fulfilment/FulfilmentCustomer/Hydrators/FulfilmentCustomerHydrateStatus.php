<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 15:19:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\Hydrators;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateCustomers;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomerHydrateStatus implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(FulfilmentCustomer $fulfilmentCustomer): string
    {
        return $fulfilmentCustomer->id;
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {
        $status = FulfilmentCustomerStatusEnum::NO_RENTAL_AGREEMENT;

        if ($fulfilmentCustomer->rentalAgreement) {
            if ($fulfilmentCustomer->rentalAgreement->state == RentalAgreementStateEnum::ACTIVE) {
                $status = $this->getStatusWhenActiveRentalAgreement($fulfilmentCustomer);
            } elseif ($fulfilmentCustomer->rentalAgreement->state == RentalAgreementStateEnum::CLOSED) {
                $status = FulfilmentCustomerStatusEnum::LOST;
            }
        } else {
            $createdAt = $fulfilmentCustomer->created_at;

            if ($createdAt->lessThan(now()->subMonths(3))) {
                $status = FulfilmentCustomerStatusEnum::UNACCOMPLISHED;
            }
        }

        $fulfilmentCustomer->update(
            ['status' => $status]
        );

        if ($fulfilmentCustomer->wasChanged()) {
            FulfilmentHydrateCustomers::run($fulfilmentCustomer->fulfilment);
        }
    }

    protected function getStatusWhenActiveRentalAgreement(FulfilmentCustomer $fulfilmentCustomer): FulfilmentCustomerStatusEnum
    {
        $status = FulfilmentCustomerStatusEnum::ACTIVE;

        $createdAt = $fulfilmentCustomer->rentalAgreement->created_at;
        if ($createdAt->lessThan($createdAt->addMonths(3))
            || $fulfilmentCustomer->number_pallets_status_storing
            || $fulfilmentCustomer->number_pallets_status_returning
            || $fulfilmentCustomer->number_pallets_status_receiving
            || $fulfilmentCustomer->number_recurring_bills_status_current

        ) {
            return FulfilmentCustomerStatusEnum::ACTIVE;
        }

        if ($fulfilmentCustomer->customer->last_invoiced_at) {
            $lastInvoicesAt = $fulfilmentCustomer->customer->last_invoiced_at;
            if ($lastInvoicesAt->lessThan($createdAt->addMonths(3))) {
                return FulfilmentCustomerStatusEnum::ACTIVE;
            }
        }

        return $status;
    }


}
