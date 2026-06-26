<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Mar 2024 23:03:23 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydratePayments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithPaymentAggregators;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $groupId): string
    {
        return $groupid ?? 'empty';
    }


    public function handle(?int $groupId): void
    {
        if (!$groupId) {
            return;
        }

        $group = Group::on('aiku_no_sticky')->find($groupId);
        if (!$group) {
            return;
        }

        $stats = array_merge(
            [
                'number_payments' => DB::connection('aiku_no_sticky')->table('payments')->whereNull('deleted_at')->where('group_id', $group->id)->count()
            ],
            $this->paidAmounts($group, 'grp_amount'),
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'type',
                enum: PaymentTypeEnum::class,
                models: Payment::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
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
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
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
                    where: function ($q) use ($group, $type) {
                        $q->where('group_id', $group->id)->where('type', $type->value);
                    },
                    connection: 'aiku_no_sticky'
                )
            );
        }
        $group->accountingStats()->update($stats);
    }
}
