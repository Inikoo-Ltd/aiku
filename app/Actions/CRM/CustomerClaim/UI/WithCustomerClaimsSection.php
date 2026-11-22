<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use Lorisleiva\Actions\ActionRequest;

/**
 * The shop's Claims section: open to who sees the shop's customers or orders, with the Dashboard,
 * Pending and Done tabs on every page.
 */
trait WithCustomerClaimsSection
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["crm.{$this->shop->id}.view", "orders.{$this->shop->id}.view"]);
    }

    public function claimsSubNavigation(Shop $shop): array
    {
        $parameters = [$shop->organisation->slug, $shop->slug];
        $pending    = CustomerClaim::where('shop_id', $shop->id)->where('state', CustomerClaimStateEnum::IN_REVIEW)->count();
        $done       = CustomerClaim::where('shop_id', $shop->id)->where('state', '!=', CustomerClaimStateEnum::IN_REVIEW)->count();

        return [
            ['route' => ['name' => 'grp.org.shops.show.claims.dashboard', 'parameters' => $parameters], 'label' => __('Dashboard'), 'leftIcon' => ['icon' => 'fal fa-tachometer-alt', 'tooltip' => __('Dashboard')]],
            ['route' => ['name' => 'grp.org.shops.show.claims.pending', 'parameters' => $parameters], 'label' => __('Pending'), 'number' => $pending, 'leftIcon' => ['icon' => 'fal fa-hourglass-half', 'tooltip' => __('Waiting for review')]],
            ['route' => ['name' => 'grp.org.shops.show.claims.done', 'parameters' => $parameters], 'label' => __('Done'), 'number' => $done, 'leftIcon' => ['icon' => 'fal fa-archive', 'tooltip' => __('Settled or rejected')]],
        ];
    }

    public function claimsBreadcrumbs(Shop $shop, ?array $last = null): array
    {
        $parameters = [$shop->organisation->slug, $shop->slug];

        return array_merge(
            ShowShop::make()->getBreadcrumbs(['organisation' => $shop->organisation->slug, 'shop' => $shop->slug]),
            [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.shops.show.claims.dashboard', 'parameters' => $parameters], 'label' => __('Claims'), 'icon' => 'fal fa-box-fragile']]],
            $last ? [['type' => 'simple', 'simple' => $last]] : []
        );
    }
}
