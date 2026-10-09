<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoReportSubscription;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Turns the weekly SEO report email on or off for the person asking: for one shop, or for every
 * website from the SEO portfolio.
 */
class ToggleSeoReportSubscription extends OrgAction
{
    private bool $isPortfolio = false;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->isPortfolio) {
            return $request->user()->hasGroupAccess();
        }

        return $request->user()->authTo([
            "websites-view.{$this->shop->organisation_id}",
            "web.{$this->shop->id}",
            "web.{$this->shop->id}.view",
            'group-webmaster.view',
        ]);
    }

    public function handle(User $user, ?Shop $shop): bool
    {
        $subscription = SeoReportSubscription::where('user_id', $user->id)->where('shop_id', $shop?->id)->first();

        if ($subscription) {
            $subscription->delete();

            return false;
        }

        SeoReportSubscription::create(['user_id' => $user->id, 'shop_id' => $shop?->id]);

        return true;
    }

    public function asController(Shop $shop, ActionRequest $request): bool
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($request->user(), $shop);
    }

    public function inPortfolio(ActionRequest $request): bool
    {
        $this->isPortfolio = true;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($request->user(), null);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
