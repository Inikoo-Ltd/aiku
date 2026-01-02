<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 23:45:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\Return\UpdateState;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\Return\ReturnStateEnum;
use App\Models\GoodsIn\OrderReturn;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\Concerns\AsAction;

class SetReturnAsReceived extends OrgAction
{
    use AsAction;

    public function handle(OrderReturn $return): OrderReturn
    {
        $return->update([
            'state'       => ReturnStateEnum::RECEIVED,
            'received_at' => now(),
        ]);

        return $return;
    }


    public function asController(OrderReturn $return): OrderReturn
    {
        return $this->handle($return);
    }

    public function htmlResponse(OrderReturn $return): \Illuminate\Http\RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.incoming.returns.show', [
            'organisation' => $return->organisation->slug,
            'warehouse'    => $return->warehouse->slug,
            'return'       => $return->slug,
        ]);
    }
}
