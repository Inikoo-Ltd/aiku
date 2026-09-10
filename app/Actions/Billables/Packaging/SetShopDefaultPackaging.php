<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Billables\Packaging;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The packaging an order gets when the customer has never chosen one.
 *
 * Held on the shop rather than as a flag per packaging, so there can only ever be one: two rows
 * both claiming to be the default would leave the choice to whichever the query returned first.
 */
class SetShopDefaultPackaging extends OrgAction
{
    public function handle(Packaging $packaging): Shop
    {
        $shop = $packaging->shop;

        if ($packaging->state !== PackagingStateEnum::ACTIVE) {
            throw ValidationException::withMessages([
                'messages' => __('Only an active packaging can be the default.'),
            ]);
        }

        $settings = $shop->settings ?? [];
        data_set($settings, 'packaging_and_inserts.default_packaging_id', $packaging->id);
        $shop->update(['settings' => $settings]);

        return $shop->refresh();
    }

    public function asController(Packaging $packaging, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($packaging->shop, $request);

        return $this->handle($packaging);
    }

    public function htmlResponse(Shop $shop): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Default packaging set'),
            'description' => __(':name is now used for customers who have not chosen their own.', [
                'name' => $shop->defaultPackaging()?->name,
            ]),
        ]);
    }
}
