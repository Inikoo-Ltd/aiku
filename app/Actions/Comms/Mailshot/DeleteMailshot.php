<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 08:48:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Comms\Mailshot;

class DeleteMailshot
{
    use AsAction;

    public function handle(Mailshot $mailshot): bool
    {
        // Note: Delete second wave if exists
        if ($mailshot->secondWave()->exists()) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }

        return $mailshot->delete();
        //  TODO: check any hydrator related to this mailshot

    }

    public function authorize(Mailshot $mailshot): bool
    {
        return true;
    }

    public function asController(Shop $shop, Mailshot $mailshot): bool
    {
        return $this->handle($mailshot);
    }
}
