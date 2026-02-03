<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Feb 2025 19:51:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Cornea\UI\Layout;

use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\SupplyChain\SupplierUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCorneaLayout
{
    use AsAction;

    public function handle($request, ?SupplierUser $supplierUser): array
    {
        $supplier    = $request->input('website');


        return [
            'supplier' => SupplierResource::make($supplier),
            'navigation' => GetCorneaNavigation::run($supplier)
        ];
    }
}
