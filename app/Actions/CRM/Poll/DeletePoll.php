<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Poll;

use App\Actions\OrgAction;
use App\Models\CRM\Poll;
use Lorisleiva\Actions\ActionRequest;

class DeletePoll extends OrgAction
{
    // TODO: raul fix the permissions
    // use WithCRMEditAuthorisation;

    public function handle(Poll $poll, bool $forceDelete): Poll
    {

        $poll->pollOptions()->delete();
        $poll->pollReplies()->delete();
        if ($forceDelete) {
            $poll->forceDelete();
        } else {
            $poll->delete();
        }
        return $poll;
    }

    public function asController(Poll $poll, ActionRequest $request)
    {
        $this->initialisationFromShop($poll->shop, $request);
        $forceDelete = $request->boolean('force_delete');
        return $this->handle($poll, $forceDelete);
    }

}
