<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Analytics\WebUserRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Logged-in visitor requests for a shop\'s website over a date range, per day and by device. Note: covers only logged-in users; anonymous visitors are not tracked.')]
class WebTrafficTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::WEB_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop' => ['required', 'string'],
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $website = $shop->website;
        if (!$website) {
            return Response::error('Shop has no website.');
        }

        $totals = WebUserRequest::where('website_id', $website->id)
            ->whereBetween('date', [$request->date('from'), $request->date('to')->endOfDay()])
            ->selectRaw('count(*) as total_requests, count(distinct web_user_id) as unique_visitors')
            ->first();

        $byDevice = WebUserRequest::where('website_id', $website->id)
            ->whereBetween('date', [$request->date('from'), $request->date('to')->endOfDay()])
            ->groupBy('device')
            ->selectRaw('device, count(*) as request_count')
            ->get()
            ->pluck('request_count', 'device')
            ->toArray();

        return Response::json([
            'website_name'      => $website->name,
            'from'              => $request->string('from'),
            'to'                => $request->string('to'),
            'total_requests'    => (int) $totals->total_requests,
            'unique_visitors'   => (int) $totals->unique_visitors,
            'by_device'         => $byDevice,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug')->required(),
            'from' => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'   => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
