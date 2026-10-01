<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterShop;
use Spatie\LaravelOptions\Options;

trait WithCompetitorForm
{
    public function competitorBlueprint(MasterShop $masterShop, ?Competitor $competitor = null): array
    {
        return [
            [
                'title'  => __('Competitor'),
                'label'  => __('Competitor'),
                'icon'   => 'fa-light fa-store-alt',
                'fields' => [
                    'name'        => [
                        'type'     => 'input',
                        'label'    => __('Name'),
                        'required' => true,
                        'value'    => $competitor?->name ?? '',
                    ],
                    'website'     => [
                        'type'        => 'input',
                        'label'       => __('Website'),
                        'placeholder' => 'https://',
                        'required'    => true,
                        'value'       => $competitor?->website ?? '',
                    ],
                    'sells_to'    => [
                        'type'     => 'select',
                        'label'    => __('Sells to'),
                        'required' => true,
                        'mode'     => 'single',
                        'options'  => Options::forArray(CompetitorSellsToEnum::labels()),
                        'value'    => $competitor?->sells_to->value ?? CompetitorSellsToEnum::WHOLESALE->value,
                    ],
                    'currency_id' => [
                        'type'       => 'select',
                        'label'      => __('Currency of their prices'),
                        'required'   => true,
                        'searchable' => true,
                        'options'    => GetCurrenciesOptions::run(),
                        'value'      => $competitor?->currency_id ?? $masterShop->group->currency_id,
                    ],
                ],
            ],
            [
                'title'  => __('Where to read prices'),
                'label'  => __('Where to read prices'),
                'icon'   => 'fa-light fa-search',
                'fields' => [
                    'feed_url'   => [
                        'type'        => 'input',
                        'label'       => __('Product feed link'),
                        'information' => $competitor?->feed_url ? __('Saved. Leave it empty to keep it') : __('Best option: the link to the CSV or text file with all their products and prices that they give their trade customers. Read every day'),
                        'value'       => '',
                    ],
                    'search_url' => [
                        'type'        => 'input',
                        'label'       => __('Search link'),
                        'information' => __('Search their website for any word, copy the link of the results page and put {query} where the word was, e.g. https://www.example.com/search?q={query}'),
                        'value'       => $competitor?->search_url ?? '',
                    ],
                    'login_url'  => [
                        'type'        => 'input',
                        'label'       => __('Login page'),
                        'information' => __('Only when they show prices to logged in trade customers'),
                        'value'       => $competitor?->login_url ?? '',
                    ],
                    'username'   => [
                        'type'  => 'input',
                        'label' => __('Login email or username'),
                        'value' => $competitor?->username ?? '',
                    ],
                    'password'   => [
                        'type'        => 'purePassword',
                        'label'       => __('Password'),
                        'information' => $competitor?->password ? __('Saved. Leave it empty to keep it') : null,
                        'value'       => '',
                    ],
                ],
            ],
        ];
    }
}
