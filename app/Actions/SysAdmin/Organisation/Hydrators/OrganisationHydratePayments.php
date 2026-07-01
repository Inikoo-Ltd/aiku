<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydratePayments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithPaymentAggregators;

    public string $jobQueue = 'hydrators-slave';


    public function getJobUniqueId(?int $organisationId): string
    {
        return $organisationId ?? 'empty';
    }

    public function handle(?int $organisationId): void
    {
        if (!$organisationId) {
            return;
        }

        $organisation = Organisation::on('aiku_no_sticky')->find($organisationId);
        if (!$organisation) {
            return;
        }

        $stats = array_merge(
            [
                'number_payments' => DB::connection('aiku_no_sticky')->table('payments')->whereNull('deleted_at')->where('organisation_id', $organisation->id)->count()

            ],
            $this->paidAmounts($organisation, 'org_amount'),
            $this->paidAmounts($organisation, 'grp_amount'),
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'type',
                enum: PaymentTypeEnum::class,
                models: Payment::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                },
                connection: 'aiku_no_sticky'
            )
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'state',
                enum: PaymentStateEnum::class,
                models: Payment::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                },
                connection: 'aiku_no_sticky'
            )
        );


        foreach (PaymentTypeEnum::cases() as $type) {
            $stats = array_merge(
                $stats,
                $this->getEnumStats(
                    model: "payments_type_{$type->snake()}",
                    field: 'state',
                    enum: PaymentStateEnum::class,
                    models: Payment::class,
                    where: function ($q) use ($organisation, $type) {
                        $q->where('organisation_id', $organisation->id)->where('type', $type->value);
                    },
                    connection: 'aiku_no_sticky'
                )
            );
        }

        $organisation->accountingStats()->update($stats);
    }
}
