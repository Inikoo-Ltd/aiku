<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Models\Ordering\Order;

class DestroyOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;

    public function handle(Order $order): Void
    {
        $order->transactions()->forceDelete();
    }
}
