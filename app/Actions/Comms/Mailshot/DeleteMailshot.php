<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 08:48:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Comms\Mailshot;

class DeleteMailshot
{
    use AsAction;

    public function handle(Mailshot $mailshot): bool
    {
        if ($mailshot->is_second_wave) {
            // NOTE: second wave cannot be deleted directly, it should be deleted via parent mailshot
            return false;
        }

        // Note: Delete second wave if exists
        if ($mailshot->secondWave()->exists()) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }

        return $mailshot->delete();

        //  TODO: check any hydrator related to this mailshot if needed

    }

    public function authorize(ActionRequest $request): bool
    {
        $shopId = $request->route('mailshot')->shop_id;

        return $request->user()->authTo([
            "crm.$shopId.edit",
            "marketing.$shopId.edit",
            "supervisor-marketing.$shopId",
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): bool
    {
        return $this->handle($mailshot);
    }
}
