<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\OutboxHasSubscribers;

use App\Actions\OrgAction;
// use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateLocations;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Comms\Outbox;
use App\Models\Comms\OutBoxHasSubscriber;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;

class DeleteOutboxHasSubscriber extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(OutBoxHasSubscriber $outBoxHasSubscriber)
    {
        $outBoxHasSubscriber->delete();
    }

    // public function authorize(ActionRequest $request): bool
    // {
    //     if ($this->asAction) {
    //         return true;
    //     }

    //     return $request->user()->authTo("locations.{$this->warehouse->id}.edit");
    // }

    public function asController(Outbox $outbox, OutBoxHasSubscriber $outBoxHasSubscriber, ActionRequest $request): void
    {
        $this->outbox = $outbox;
        $this->initialisationFromShop($outbox->shop, $request);

        $this->handle($outBoxHasSubscriber);
    }

}
