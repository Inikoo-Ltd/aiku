<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\UpcomingTransaction;
use Lorisleiva\Actions\ActionRequest;

class DeleteUpcomingTransaction extends OrgAction
{
    use WithActionUpdate;

    public function handle(UpcomingTransaction $upcomingTransaction): UpcomingTransaction
    {
        $upcomingTransaction->delete();

        return $upcomingTransaction;
    }

    public function action(UpcomingTransaction $upcomingTransaction): UpcomingTransaction
    {
        $this->asAction = true;
        $this->initialisationFromShop($upcomingTransaction->shop, []);

        return $this->handle($upcomingTransaction);
    }

    public function asController(UpcomingTransaction $upcomingTransaction, ActionRequest $request): UpcomingTransaction
    {
        $this->initialisationFromShop($upcomingTransaction->shop, $request);

        return $this->handle($upcomingTransaction);
    }
}
