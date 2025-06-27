<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\PollOption;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePolls;
use App\Actions\CRM\Poll\Hydrate\PollHydrateCustomers;
use App\Actions\OrgAction;
use App\Models\CRM\PollOption;

class DeletePollOptions extends OrgAction
{
    public function handle(PollOption $pollOption, bool $forceDelete): PollOption
    {
        $poll = $pollOption->poll;

        $pollOption->pollReplies()->delete();

        if ($forceDelete) {
            $pollOption->forceDelete();
        } else {
            $pollOption->delete();
        }

        ShopHydratePolls::dispatch($poll->shop);
        PollHydrateCustomers::dispatch($poll);

        return $pollOption;
    }
}
