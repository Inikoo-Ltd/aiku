<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrders implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $groups = DB::table('orders')
            ->selectRaw('state, status, handing_type, deleted_at is null as is_live, count(*) as total')
            ->where('organisation_id', $organisation->id)
            ->groupByRaw('1, 2, 3, 4')
            ->get();

        $liveGroups = $groups->where('is_live', true);

        $stats = [
            'number_orders' => (int) $groups->sum('total'),
        ];

        foreach (['state' => OrderStateEnum::class, 'status' => OrderStatusEnum::class, 'handing_type' => OrderHandingTypeEnum::class] as $field => $enum) {
            foreach ($enum::cases() as $case) {
                $stats["number_orders_{$field}_".$case->snake()] = (int) $liveGroups->where($field, $case->value)->sum('total');
            }
        }

        $organisation->orderingStats()->update($stats);
    }
}
