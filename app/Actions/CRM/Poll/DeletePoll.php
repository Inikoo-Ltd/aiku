<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Poll;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePolls;
use App\Actions\OrgAction;
use App\Models\CRM\Poll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
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
        ShopHydratePolls::dispatch($poll->shop);

        return $poll;
    }

    public function asController(Poll $poll, ActionRequest $request): Poll
    {
        $this->initialisationFromShop($poll->shop, $request);
        $forceDelete = $request->boolean('force_delete');
        return $this->handle($poll, $forceDelete);
    }

    public function htmlResponse(Poll $poll): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.polls.index', [
            'organisation' => $poll->organisation->slug,
            'shop'         => $poll->shop->slug
        ]);
    }

}
