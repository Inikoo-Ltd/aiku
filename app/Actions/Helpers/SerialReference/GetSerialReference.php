<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Apr 2023 12:27:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\SerialReference;

use App\Models\Accounting\PaymentAccount;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\SerialReference;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;

class GetSerialReference
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(Shop|FulfilmentCustomer|PaymentAccount|Fulfilment|Organisation $container, $modelType): string
    {
        /** @var SerialReference $serialReference */
        $serialReference = $container->serialReferences()->where('model', $modelType)->firstOrFail();

        $serial = DB::transaction(function () use ($serialReference) {
            $res = DB::table('serial_references')->select('serial')
                ->where('id', $serialReference->id)
                ->lockForUpdate()
                ->first();

            $serial = (int)$res->serial + 1;

            DB::table('serial_references')
                ->where('id', $serialReference->id)
                ->update(['serial' => $serial]);
            return $serial;
        });

        return sprintf($serialReference->format, $serial);
    }



}
