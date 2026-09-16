<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\AdProposalStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceAdProposal;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns a suggestion down, for good.
 *
 * The row is kept rather than deleted so its fingerprint stays on file, and tonight's run recognises
 * the same suggestion and says nothing. That is the difference between a page people keep opening and
 * one that shows them the same rejected ideas every morning until they stop looking.
 */
class DismissAdProposal extends OrgAction
{
    public function handle(TrafficSourceAdProposal $proposal): TrafficSourceAdProposal
    {
        if ($proposal->state !== AdProposalStateEnum::OPEN) {
            throw ValidationException::withMessages([
                'proposal' => __('This suggestion has already been dealt with.'),
            ]);
        }

        $proposal->update([
            'state'              => AdProposalStateEnum::DISMISSED,
            'decided_by_user_id' => request()->user()?->id,
            'decided_at'         => now(),
        ]);

        return $proposal->refresh();
    }

    public function asController(Organisation $organisation, Shop $shop, TrafficSourceAdProposal $trafficSourceAdProposal, ActionRequest $request): TrafficSourceAdProposal
    {
        if ($trafficSourceAdProposal->shop_id !== $shop->id) {
            throw new NotFoundHttpException();
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($trafficSourceAdProposal);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
