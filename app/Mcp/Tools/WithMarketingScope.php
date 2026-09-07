<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Marketing permissions are shop-scoped (`marketing.<shop_id>.view`) and nothing broader exists, so
 * an organisation or group roll-up is readable by whoever may read marketing for any shop under it,
 * the same rule the marketing dashboards apply.
 */
trait WithMarketingScope
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::MARKETING_VIEW;
    }

    protected function marketingScope(Request $request): Shop|Organisation|Group|Response
    {
        if ($request->string('shop')->isNotEmpty()) {
            return $this->authorisedShop($request) ?? $this->shopNotFoundError($request);
        }

        if ($request->string('organisation')->isNotEmpty()) {
            $identifier   = strtolower((string) $request->string('organisation'));
            $organisation = Organisation::where(function ($query) use ($identifier) {
                $query->whereRaw('lower(slug) = ?', [$identifier])
                    ->orWhereRaw('lower(code) = ?', [$identifier])
                    ->orWhereRaw('lower(name) = ?', [$identifier]);
            })->first();

            if ($organisation && $this->canReadMarketingOfAnyShop($request, $organisation->shops()->pluck('id')->all())) {
                return $organisation;
            }

            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $group = $request->user()?->group;

        if ($group && $this->canReadMarketingOfAnyShop($request, $group->shops()->pluck('id')->all())) {
            return $group;
        }

        return Response::error('You do not have marketing access to any shop. Pass a shop or organisation you may read.');
    }

    /**
     * @param array<int, int> $shopIds
     */
    private function canReadMarketingOfAnyShop(Request $request, array $shopIds): bool
    {
        foreach ($shopIds as $shopId) {
            if ($this->userCan($request, "marketing.$shopId.view")) {
                return true;
            }
        }

        return false;
    }

    /**
     * The requested period and the equal-length period right before it. Thirty days back when no
     * start is given, which matches how the dashboards open.
     *
     * @return array{from: Carbon, to: Carbon, previous_from: Carbon, previous_to: Carbon}
     */
    protected function marketingPeriod(Request $request): array
    {
        $to   = $request->string('to')->isNotEmpty() ? $request->date('to')->endOfDay() : now()->endOfDay();
        $from = $request->string('from')->isNotEmpty() ? $request->date('from')->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        $days = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;

        return [
            'from'          => $from,
            'to'            => $to,
            'previous_from' => $from->copy()->subDays($days),
            'previous_to'   => $from->copy()->subSecond(),
        ];
    }

    protected function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
