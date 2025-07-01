<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 01-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Poll;

use App\Actions\CRM\Poll\DeletePoll;
use App\Actions\OrgAction;
use App\Models\CRM\Poll;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaPoll extends OrgAction
{
    public function asController(CustomerSalesChannel $customerSalesChannel, Poll $poll, ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisationFromShop($poll->shop, $request);
        $forceDelete = $request->boolean('force_delete');

        DeletePoll::run(
            $poll,
            $forceDelete
        );
        return $customerSalesChannel;
    }

    public function htmlResponse(CustomerSalesChannel $customerSalesChannel): RedirectResponse
    {
        return Redirect::route('retina.dropshipping.customer_sales_channels.polls.index', [
            'customerSalesChannel' => $customerSalesChannel->slug
        ]);
    }

}
