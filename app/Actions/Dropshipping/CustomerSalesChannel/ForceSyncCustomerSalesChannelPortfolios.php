<?php

/*
 * Author: Artha <dev@aw-advantage.com>
 * Created: Sun, 14 Sept 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Lets staff force a channel to resend its portfolios without waiting for the next automatic run,
 * which until now only the customer could trigger from their own portal (HELP-3001).
 */
class ForceSyncCustomerSalesChannelPortfolios extends OrgAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): ?CustomerSalesChannel
    {
        return SyncCustomerSalesChannelPortfolios::run($customerSalesChannel);
    }

    public function action(CustomerSalesChannel $customerSalesChannel): ?CustomerSalesChannel
    {
        $this->asAction = true;
        $this->initialisation($customerSalesChannel->organisation, []);

        return $this->handle($customerSalesChannel);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): ?CustomerSalesChannel
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel);
    }

    public function htmlResponse(?CustomerSalesChannel $customerSalesChannel): RedirectResponse
    {
        if (!$customerSalesChannel) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Force sync failed'),
                'description' => __('This channel is not connected to the platform, so nothing can be sent.'),
            ]);
        }

        if (SyncCustomerSalesChannelPortfolios::hasNothingToSend($customerSalesChannel)) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Nothing to sync'),
                'description' => __('None of these products has been uploaded to the channel yet, so a sync has nothing to send. Use Create New Product or Match With Existing Product first.'),
            ]);
        }

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Force sync started'),
            'description' => __('Portfolios are being pushed to :channel. This may take a few minutes to complete.', [
                'channel' => $customerSalesChannel->name ?? $customerSalesChannel->reference
            ]),
        ]);
    }
}
