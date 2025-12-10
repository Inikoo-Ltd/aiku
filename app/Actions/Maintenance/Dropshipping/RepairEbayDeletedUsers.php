<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayDeletedUsers
{
    use AsAction;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser): void
    {
        if (!$ebayUser->customerSalesChannel) {
            $ebayUser->delete();

            echo $ebayUser->name . "-" . $ebayUser->step->value . "\n";
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:ebay_deleted_users_corrupt';
    }

    public function asCommand(): void
    {
        $ebayUsers = EbayUser::all();

        foreach ($ebayUsers as $ebayUser) {
            $this->handle($ebayUser);
        }
    }
}
