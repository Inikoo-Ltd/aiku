<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesIntervals;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsOrganisations
{
    use AsAction;

    public string $commandSignature = 'aiku:process-reset-intervals-organisations';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        /** @var Organisation $organisation */
        foreach (Organisation::whereNot('type', OrganisationTypeEnum::AGENT)->get() as $organisation) {
            if (array_intersect($this->getIntervalValues($intervals), [
                DateIntervalEnum::YESTERDAY->value,
                DateIntervalEnum::TODAY->value
            ])) {
                OrganisationHydrateOrderStateFinalised::dispatch($organisation->id);
                OrganisationHydrateOrdersDispatchedToday::dispatch($organisation->id);
            }

            OrganisationHydrateSalesIntervals::dispatch(
                organisation: $organisation,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            OrganisationHydrateInvoiceIntervals::dispatch(
                organisation: $organisation,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            OrganisationHydrateRegistrationIntervals::dispatch(
                organisationId: $organisation->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            OrganisationHydrateOrderIntervals::dispatch(
                organisation: $organisation,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            OrganisationHydrateOrderInBasketAtCreatedIntervals::dispatch(
                organisation: $organisation,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                organisation: $organisation,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }
    }

    private function getIntervalValues(array $intervals): array
    {
        return array_map(static function ($interval) {
            if ($interval instanceof DateIntervalEnum) {
                return $interval->value;
            }

            return $interval;
        }, $intervals);
    }
}
