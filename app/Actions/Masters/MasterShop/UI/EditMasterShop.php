<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterShop\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterShop\WithMasterShopNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterShop extends OrgAction
{
    use WithMasterShopNavigation;
    use WithMastersEditAuthorisation;

    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterShop, $request);
    }

    public function handle(MasterShop $masterShop, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterShop
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($masterShop, $request),
                    'next'     => $this->getNextModel($masterShop, $request),
                ],
                'title'       => __('Edit master shop').': '.$masterShop->code,
                'pageHead'    => [
                    'title'   => __('Edit master shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => array_values(array_filter([
                        [
                            'label'  => __('Id'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $masterShop->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $masterShop->name
                                ]
                            ]
                        ],
                        $this->canEditPrices ? [
                            'label'  => __('Pricing'),
                            'icon'   => 'fa-light fa-money-bill',
                            'fields' => [
                                'price_exchanges' => [
                                    'type'        => 'master_shop_price_exchanges',
                                    'label'       => __('Currencies'),
                                    'full'        => true,
                                    'noSaveButton' => true,
                                    'value'       => $masterShop->price_exchanges,
                                    'currencies_shops' => $this->getCurrenciesShops($masterShop),
                                    'updateRoute' => [
                                        'name'       => 'grp.models.master_shops.price_exchange.update',
                                        'parameters' => [
                                            'masterShop' => $masterShop->id
                                        ]
                                    ]
                                ]
                            ]
                        ] : null,
                        $this->canEditOffers ? [
                            'label'  => __('Offers'),
                            'icon'   => 'fa-light fa-badge-percent',
                            'fields' => [
                                'gold_reward_eligible' => [
                                    'type'  => 'toggle',
                                    'label' => __('Enable gold reward'),
                                    'value' => $masterShop->gold_reward_eligible
                                ],
                            ]
                        ] : null,
                    ])),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_shops.update',
                            'parameters' => [
                                'masterShop' => $masterShop->id
                            ]
                        ],
                    ],
                ]
            ]
        );
    }

    /** @return array<string, array{shops: array<int, string>, number_products: int}> */
    protected function getCurrenciesShops(MasterShop $masterShop): array
    {
        $currenciesShops = [];

        $shops = Shop::where('master_shop_id', $masterShop->id)
            ->where('state', ShopStateEnum::OPEN)
            ->with(['currency', 'stats'])
            ->get();

        foreach ($shops as $shop) {
            $currencyCode = $shop->currency->code;
            $currenciesShops[$currencyCode]['shops'][]        = $shop->name;
            $currenciesShops[$currencyCode]['number_products'] =
                ($currenciesShops[$currencyCode]['number_products'] ?? 0) + (int)$shop->stats?->number_current_products;
        }

        $currencies = $shops->pluck('currency')->unique('id');
        foreach ($currencies as $currency) {
            foreach ($currencies as $baseCurrency) {
                if ($baseCurrency->id == $currency->id) {
                    continue;
                }
                $currenciesShops[$currency->code]['real_exchanges'][$baseCurrency->code] =
                    GetCurrencyExchange::run($baseCurrency, $currency);
            }
        }

        return $currenciesShops;
    }

    public function getBreadcrumbs(MasterShop $masterShop): array
    {
        return ShowMasterShop::make()->getBreadcrumbs(
            $masterShop,
            suffix: '('.__('Editing').')'
        );
    }

}
