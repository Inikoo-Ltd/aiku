<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\ChatSession\GetChatReports;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The reports page is the same in group, organisation and shop scope; only the set of shops
 * it counts differs.
 */
trait WithChatReportsResponse
{
    /**
     * @param  Collection<int, Shop>  $shops
     */
    protected function chatReportsProps(Collection $shops): array
    {
        $reports  = GetChatReports::make();
        $interval = $reports->intervalFromRequest();
        $title    = __('Chat Reports');

        $conversationsRouteName = str_replace('.reports', '.conversations.show', (string) request()->route()?->getName());
        $phoneCallsRouteName    = str_replace('.reports', '.phone_calls.index', (string) request()->route()?->getName());

        $shopNames = $shops->mapWithKeys(fn (Shop $shop) => [$shop->id => ['name' => $shop->name, 'slug' => $shop->slug]])->all();

        return [
            'title'     => $title,
            'pageHead'  => [
                'title' => $title,
                'icon'  => [
                    'icon'  => ['fal', 'fa-chart-line'],
                    'title' => $title,
                ],
                'actions' => Route::has($phoneCallsRouteName) ? [
                    [
                        'type'  => 'button',
                        'style' => 'tertiary',
                        'icon'  => 'fal fa-phone',
                        'label' => __('Phone calls'),
                        'route' => [
                            'name'       => $phoneCallsRouteName,
                            'parameters' => request()->route()->originalParameters(),
                        ],
                    ],
                ] : [],
            ],
            'stats'     => $reports->handle($shops->pluck('id'), $interval, $shopNames),
            'intervals' => $reports->intervalOptions(),
            'showShops' => $shops->count() > 1,
            'conversationsRoute' => Route::has($conversationsRouteName) ? [
                'name'       => $conversationsRouteName,
                'parameters' => request()->route()->originalParameters(),
            ] : null,
        ];
    }
}
