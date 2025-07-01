<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 01-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Poll\UI;

use App\Actions\CRM\Poll\DeletePoll;
use App\Actions\OrgAction;
use App\Models\CRM\Poll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaPoll extends OrgAction
{
    public function asController(Poll $poll, ActionRequest $request): Poll
    {
        $this->initialisationFromShop($poll->shop, $request);
        $forceDelete = $request->boolean('force_delete');

        return DeletePoll::run(
            $poll,
            $forceDelete
        );
    }

    public function htmlResponse(Poll $poll): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.polls.index', [
            'organisation' => $poll->organisation->slug,
            'shop'         => $poll->shop->slug
        ]);
    }

}
