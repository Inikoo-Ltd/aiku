<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateProductSuppliers
{
    use AsAction;

    private Group $group;

    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }

    public function handle(Group $group): void
    {
        $stats = [

            'number_supplier_products'                                => SupplierProduct::count(),
            'number_current_supplier_products'                        => SupplierProduct::whereIn(
                'supplier_products.state',
                [
                    SupplierProductStateEnum::ACTIVE,
                    SupplierProductStateEnum::DISCONTINUING
                ]
            )
                ->count(),
        ];


        $stateCounts = SupplierProduct::selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')->all();
        foreach (SupplierProductStateEnum::cases() as $productState) {
            $stats['number_supplier_products_state_'.$productState->snake()] = Arr::get($stateCounts, $productState->value, 0);
        }



        $group->supplyChainStats()->update($stats);
    }


}
