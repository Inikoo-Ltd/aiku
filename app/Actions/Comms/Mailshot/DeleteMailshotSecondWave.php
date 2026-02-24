<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 5 Feb 2026 15:12:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Comms\Mailshot;

class DeleteMailshotSecondWave
{
    use AsAction;

    public function handle(Mailshot $mailshot): bool
    {
        return $mailshot->delete();
        //  TODO: check any hydrator related to this mailshot if needed
    }

    public function asController(Shop $shop, Mailshot $mailshot): bool
    {
        return $this->handle($mailshot);
    }
}
