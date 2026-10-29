<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Thu, 15 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Actions\Web\Webpage\GetWebpageStructuredDataSource;
use App\Actions\Web\Webpage\UI\WithWebpageSeoImageField;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The webmaster's view of a product in its shop: what the shop follows from the master, how its
 * webpage appears in search, the SEO team's review and its implementation, and the issues the
 * site audit found. ?tab= opens one of those.
 */
class ShowProductSetting extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithShopPpcPermissions;
    use WithWebpageSeoImageField;

    public const array TABS = ['setting', 'seo_review', 'validation', 'issues'];

    public function handle(Product $product): Product
    {
        return $product;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): Product
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        $product->loadMissing(['shop.currency', 'shop.website', 'organisation', 'webpage.website', 'currency', 'masterProduct.masterFamily']);

        $tab = $request->query('tab');
        if (!in_array($tab, self::TABS, true)) {
            $tab = self::TABS[0];
        }

        $productRoute = [
            'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
            'parameters' => [
                'organisation' => $product->organisation->slug,
                'shop'         => $product->shop->slug,
                'product'      => $product->slug,
            ],
        ];

        return Inertia::render(
            'Org/Catalogue/ProductSetting',
            [
                'title'          => __('Product setting').': '.$product->code,
                'breadcrumbs'    => ShowProduct::make()->getBreadcrumbs(
                    $product->shop,
                    $product,
                    'grp.org.shops.show.catalogue.products.all_products.show',
                    $request->route()->originalParameters(),
                    '('.__('Product setting').')'
                ),
                'pageHead'       => [
                    'model'      => __('Product setting'),
                    'title'      => $product->code,
                    'afterTitle' => [
                        'label' => $product->name,
                    ],
                    'icon'       => [
                        'icon'  => ['fal', 'fa-sliders-h'],
                        'title' => __('Product setting'),
                    ],
                    'actions'    => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => $productRoute,
                        ]
                    ],
                ],
                'tab'            => $tab,
                'canEdit'        => $this->canEdit,
                'overview_route' => $productRoute,
                'master'         => $this->getMasterData($product),
                'shop'           => $this->getShopData($product, $request->user()),
            ]
        );
    }

    /**
     * What the product follows: its master product, or the product itself when it has no master.
     *
     * @return array<string, mixed>
     */
    private function getMasterData(Product $product): array
    {
        $source = $product->masterProduct ?? $product;

        $dimensions = collect(Arr::only($source->marketing_dimensions ?? [], ['l', 'w', 'h']))
            ->filter(fn ($value) => (float)$value > 0)
            ->map(fn ($metres) => round((float)$metres * 100, 2))
            ->implode(' × ');

        return [
            'has_master'        => $source instanceof MasterAsset,
            'code'              => $source->code,
            'name'              => $source->name,
            'description'       => $source->description,
            'description_extra' => $source->description_extra,
            'meta_description'  => $source instanceof MasterAsset ? $source->meta_description : null,
            'price'             => $source->price,
            'currency_code'     => $source instanceof MasterAsset ? $source->group->currency->code : ($product->currency?->code ?? $product->shop->currency->code),
            'marketing_weight'  => $source->marketing_weight,
            'gross_weight'      => $source->gross_weight,
            'dimensions'        => $dimensions !== '' ? $dimensions.' cm' : null,
            'ingredients'       => $source->marketing_ingredients,
            'barcode'           => $source->barcode ?: $source->tradeUnits->pluck('barcode')->filter()->first(),
            'origin_country'    => $source->origin_country_id ? Country::find($source->origin_country_id)?->name : $source->country_of_origin,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getShopData(Product $product, User $user): array
    {
        $webpage      = $product->webpage;
        $shop         = $product->shop;
        $masterAsset  = $product->masterProduct;
        $audit        = $webpage ? GetProductWebpageAudit::run($webpage) : null;

        return [
            'id'            => $product->id,
            'shop'          => [
                'code'          => $shop->code,
                'name'          => $shop->name,
                'language_code' => $shop->language->code,
            ],
            'can_edit_seo'  => $this->canEditSeo($user, $shop),
            'product'       => [
                'id'                => $product->id,
                'code'              => $product->code,
                'name'              => $product->name,
                'description'       => $product->description,
                'description_extra' => $product->description_extra,
                'price'             => $product->price,
                'currency_code'     => $product->currency?->code ?? $shop->currency->code,
                'follows_master'    => [
                    'name'              => $masterAsset && $product->name === $masterAsset->name,
                    'description'       => $masterAsset && $product->description === $masterAsset->description,
                    'description_extra' => $masterAsset && $product->description_extra === $masterAsset->description_extra,
                    'price'             => $masterAsset && !$product->not_follow_master_prices,
                ],
                'route'             => [
                    'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
                    'parameters' => [
                        'organisation' => $product->organisation->slug,
                        'shop'         => $shop->slug,
                        'product'      => $product->slug,
                    ],
                ],
                'update_route'      => [
                    'name'       => 'grp.models.product.update',
                    'parameters' => ['product' => $product->id],
                ],
            ],
            'webpage'       => $webpage ? [
                'id'               => $webpage->id,
                'state'            => $webpage->state->value,
                'title'            => $webpage->title,
                'description'      => $webpage->description,
                'url'              => $webpage->url,
                'page_url'         => $webpage->canonical_url ?: ($webpage->website ? $webpage->getUrl() : null),
                'breadcrumb_label' => $webpage->breadcrumb_label,
                'index_page'       => (bool)$webpage->index_page,
                'follow_link'      => (bool)$webpage->follow_link,
                'structured_data'  => Arr::get($webpage->seo_data, 'structured_data'),
                'structured_data_source' => GetWebpageStructuredDataSource::run($webpage),
                'seo_image_field'  => $this->webpageSeoImageField($webpage),
                'state_field'      => $this->getStateField($product, $webpage),
                'update_route'     => [
                    'name'       => 'grp.models.webpage.update',
                    'parameters' => ['webpage' => $webpage->id],
                ],
                'publish_route'    => [
                    'name'       => 'grp.models.webpage.publish',
                    'parameters' => ['webpage' => $webpage->id],
                ],
                'workshop_route'   => $webpage->website ? [
                    'name'       => 'grp.org.shops.show.web.webpages.workshop',
                    'parameters' => [
                        'organisation' => $product->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $webpage->website->slug,
                        'webpage'      => $webpage->slug,
                    ],
                ] : null,
                'crawl_route'      => $webpage->website && $this->canEditSeo($user, $shop) ? [
                    'name'       => 'grp.models.product.webpage_check.store',
                    'parameters' => ['product' => $product->id],
                    'method'     => 'post',
                ] : null,
            ] : null,
            'checklist'     => GetProductChecklist::run($product, $audit['checks'] ?? null),
            'workflow'      => GetProductWorkflow::run($product),
            'review'        => $webpage ? GetProductSeoReview::run($webpage, $product, $user) : null,
            'audit'         => $audit ? Arr::except($audit, ['checks', 'crawl_page']) : null,
            'shared'        => $this->getSharedInformation($product),
        ];
    }

    /**
     * The "Set online/closed" field of EditWebpage: closing the page needs the webpage its visitors
     * are redirected to. A product in a variant follows its variant, so its state is not offered.
     *
     * @return array<string, mixed>
     */
    private function getStateField(Product $product, Webpage $webpage): array
    {
        $storefront = Webpage::where('type', WebpageTypeEnum::STOREFRONT)->where('shop_id', $webpage->shop_id)->first();

        return [
            'can_change'          => !$product->variant_id,
            'redirect_webpage_id' => $webpage->redirect_webpage_id,
            'default_storefront'  => $storefront ? getFieldWebpageData($storefront) : null,
            'init_options'        => $webpage->redirectWebpage ? [getFieldWebpageData($webpage->redirectWebpage)] : null,
            'fetch_params'        => [
                'organisation' => $product->organisation->slug,
                'shop'         => $product->shop->slug,
                'website'      => $webpage->website?->slug,
                'webpage'      => $webpage->slug,
            ],
        ];
    }

    /**
     * Written once on the master family for all its products. Until the master sets it, the
     * website's own texts are shown: the delivery text of its product template and the return
     * policy of its settings.
     *
     * @return array{shipping: ?array{value: string, source: string}, return_policy: ?array{value: string, source: string}, website_edit_route: ?array}
     */
    private function getSharedInformation(Product $product): array
    {
        $website      = $product->shop->website;
        $masterShared = Arr::get($product->masterProduct?->masterFamily?->data ?? [], 'shared_information', []);

        $htmlOrNull = fn (?string $html) => trim(html_entity_decode(strip_tags($html ?? ''))) !== '' ? $html : null;
        $pick       = fn (?string $fromMaster, ?string $fromWebsite) => match (true) {
            $htmlOrNull($fromMaster) !== null  => ['value' => $fromMaster, 'source' => 'master'],
            $htmlOrNull($fromWebsite) !== null => ['value' => $fromWebsite, 'source' => 'website'],
            default                            => null,
        };

        return [
            'shipping'           => $pick(Arr::get($masterShared, 'shipping'), Arr::get($website?->published_layout, 'product.data.fieldValue.delivery_info.text')),
            'return_policy'      => $pick(Arr::get($masterShared, 'return_policy'), Arr::get($website?->settings, 'return_policy')),
            'website_edit_route' => $website ? [
                'name'       => 'grp.org.shops.show.web.websites.edit',
                'parameters' => [
                    'organisation' => $product->organisation->slug,
                    'shop'         => $product->shop->slug,
                    'website'      => $website->slug,
                ],
            ] : null,
        ];
    }
}
