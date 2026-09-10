<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 01:32:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Webpage;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\EnumHelperTrait;
use Illuminate\Support\Arr;

enum WebpageSubTypeEnum: string
{
    use EnumHelperTrait;

    case STOREFRONT = 'storefront';
    case CATALOGUE = 'catalogue';
    case PRODUCTS = 'products';
    case PRODUCT = 'product';
    case FAMILY = 'family';
    case DEPARTMENT = 'department';
    case SUB_DEPARTMENT = 'sub_department';
    case COLLECTION = 'collection';
    case LANDING_PAGE = 'landing_page';
    case CONTENT = 'content';
    case ABOUT_US = 'about-us';
    case CONTACT = 'contact';
    case RETURNS = 'returns';
    case SHIPPING = 'shipping';
    case SHOWROOM = 'showroom';
    case TERMS_AND_CONDITIONS = 'terms-and-conditions';
    case PRIVACY = 'privacy';
    case COOKIES_POLICY = 'cookies-policy';
    case BASKET = 'basket';
    case CHECKOUT = 'checkout';
    case LOGIN = 'login';
    case REGISTER = 'register';
    case CALL_BACK = 'call_back';
    case APPOINTMENT = 'appointment';
    case PRICING = 'pricing';
    case ARTICLE = 'article';
    case MAILSHOT = 'mailshot';

    /** System Sub Type */
    case LOGIN_PAGE = "login_page";
    case REGISTER_PAGE = "register_page";
    case FORGOT_PASSWORD_PAGE = "forgot_password_page";
    case BLOG_DASHBOARD_PAGE = "blog_dashboard_page";

    /** Legacy catch all still stored on existing webpages, resolved by resolveBlogCategory. */
    case BLOG = 'blog';
    case NEWSLETTERS    = 'newsletters';
    case PRODUCT_GUIDES = 'product_guides';
    case BUSINESS_TIPS  = 'business_tips';
    case INTEGRATIONS_GUIDES = 'integrations_guides';
    case DROPSHIPPING_GUIDES = 'dropshipping_guides';

    /**
     * System pages that back a website column, keyed by sub type.
     *
     * @return array<string, array{web_block: string, website_field: string, url: string, title: string}>
     */
    public static function systemPages(): array
    {
        return [
            self::LOGIN_PAGE->value           => ['web_block' => 'login', 'website_field' => 'login_page_id', 'url' => 'login', 'title' => 'Login'],
            self::REGISTER_PAGE->value        => ['web_block' => 'register', 'website_field' => 'register_page_id', 'url' => 'register', 'title' => 'Register'],
            self::FORGOT_PASSWORD_PAGE->value => ['web_block' => 'forgot-password', 'website_field' => 'forgot_password_page_id', 'url' => 'forgot-password', 'title' => 'Forgot Password'],
            self::BLOG_DASHBOARD_PAGE->value  => ['web_block' => 'blog-categories', 'website_field' => 'blog_dashboard_page_id', 'url' => 'blog', 'title' => 'Our Blog'],
        ];
    }

    public static function labels(): array
    {
        return [
            'storefront'            => __('Storefront'),
            'appointment'           => __('Appointment'),
            'login'                 => __('Login'),
            'register'              => __('Register'),
            'mailshot'              => __('Mailshot'),
            'article'               => __('Article'),
            'content'               => __('Content'),

            'blog'                  => __('Blog'),
            'newsletters'           => __('Newsletters'),
            'product_guides'        => __('Product Guides'),
            'business_tips'         => __('Business Tips'),
            'integrations_guides'   => __('Integrations Guides'),
            'dropshipping_guides'   => __('Dropshipping Guides'),

            'login_page'            => __('Login'),
            'register_page'         => __('Register'),
            'forgot_password_page'  => __('Forgot Password'),
            'blog_dashboard_page'   => __('Blog Dashboard'),
        ];
    }

    /**
     * Blog categories offered by a shop type. A dropshipping shop writes about a different set of
     * subjects than a shop selling its own catalogue, so each type is given its own categories.
     *
     * @return array<int, self>
     */
    public static function blogCategories(?ShopTypeEnum $shopType = null): array
    {
        if ($shopType === ShopTypeEnum::DROPSHIPPING) {
            return [
                self::INTEGRATIONS_GUIDES,
                self::DROPSHIPPING_GUIDES,
                self::PRODUCT_GUIDES,
            ];
        }

        return [
            self::NEWSLETTERS,
            self::PRODUCT_GUIDES,
            self::BUSINESS_TIPS,
        ];
    }

    /**
     * Blog categories of every shop type, used to read sub types already stored on webpages, which
     * keep their category when the shop they belong to is not at hand.
     *
     * @return array<int, self>
     */
    public static function allBlogCategories(): array
    {
        $categories = self::blogCategories();

        foreach (ShopTypeEnum::cases() as $shopType) {
            foreach (self::blogCategories($shopType) as $category) {
                if (!in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }
        }

        return $categories;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function blogCategoriesWithLabel(?ShopTypeEnum $shopType = null): array
    {
        $labels = self::labels();

        return array_map(
            fn (self $category): array => [
                'value' => $category->value,
                'label' => Arr::get($labels, $category->value, $category->value),
            ],
            self::blogCategories($shopType)
        );
    }

    /**
     * Iris path of the dashboard listing each blog category.
     *
     * @return array<string, string>
     */
    public static function blogCategoryUrls(): array
    {
        return [
            self::NEWSLETTERS->value    => '/david-aw-news',
            self::PRODUCT_GUIDES->value => '/product-guides',
            self::BUSINESS_TIPS->value  => '/business-tips',
            self::INTEGRATIONS_GUIDES->value => '/integrations-guides',
            self::DROPSHIPPING_GUIDES->value => '/dropshipping-guides',
        ];
    }

    public function blogCategoryUrl(): ?string
    {
        return self::blogCategoryUrls()[$this->value] ?? null;
    }

    /**
     * Sub types no longer backed by an enum case but still stored on existing webpages,
     * mapped to the blog category they are read as.
     *
     * @return array<string, self>
     */
    public static function legacyBlogCategoryAliases(): array
    {
        return [
            'blog'               => self::PRODUCT_GUIDES,
            'insight'            => self::BUSINESS_TIPS,
            'tips'               => self::BUSINESS_TIPS,
            'davids_travel_blog' => self::NEWSLETTERS,
            'david_aw_news'      => self::NEWSLETTERS,
        ];
    }

    /**
     * Legacy sub types that were used as a catch all and therefore do not identify a blog
     * category on their own; they are read as the alias they are mapped to. A dropshipping shop
     * offers a single guide category the catch all can belong to, so there it decides a category
     * like any other sub type and can be persisted as one.
     *
     * @return array<int, string>
     */
    public static function ambiguousBlogSubTypes(?ShopTypeEnum $shopType = null): array
    {
        if ($shopType === ShopTypeEnum::DROPSHIPPING) {
            return [];
        }

        return ['blog'];
    }

    /**
     * Resolves the blog category of a webpage. A catch all sub type does not identify a category on
     * its own and is read as its alias, unless $withAmbiguousFallback is disabled, which callers
     * persisting the result use to leave undecidable webpages alone. Which sub types are undecidable
     * depends on the shop type, so a shop offering a single home for the catch all decides it here.
     */
    public static function resolveBlogCategory(?string $subType, bool $withAmbiguousFallback = true, ?ShopTypeEnum $shopType = null): ?self
    {
        if ($subType === null) {
            return null;
        }

        $aliases = self::legacyBlogCategoryAliases();

        if (in_array($subType, self::ambiguousBlogSubTypes($shopType), true)) {
            return $withAmbiguousFallback ? ($aliases[$subType] ?? null) : null;
        }

        $category = self::tryFrom($subType);

        if ($category && in_array($category, self::allBlogCategories(), true)) {
            return $category;
        }

        return $aliases[$subType] ?? null;
    }

    /**
     * SQL expression resolving the blog category of a webpage row, applying the same rules as
     * resolveBlogCategory so queries and counts read the legacy sub types the same way.
     */
    public static function blogCategorySqlExpression(string $table = 'webpages'): string
    {
        $quote = fn (string $value): string => "'".str_replace("'", "''", $value)."'";
        $list  = fn (array $values): string => implode(', ', array_map($quote, $values));

        $ambiguous = self::ambiguousBlogSubTypes();
        $aliases   = self::legacyBlogCategoryAliases();
        $branches  = [];

        foreach (self::allBlogCategories() as $category) {
            $storedValues = [$category->value];

            foreach ($aliases as $legacyValue => $aliasedCategory) {
                if ($aliasedCategory === $category && !in_array($legacyValue, $ambiguous, true)) {
                    $storedValues[] = $legacyValue;
                }
            }

            $branches[] = "WHEN $table.sub_type IN ({$list($storedValues)}) THEN {$quote($category->value)}";
        }

        foreach ($ambiguous as $legacyValue) {
            if (isset($aliases[$legacyValue])) {
                $branches[] = "WHEN $table.sub_type = {$quote($legacyValue)} THEN {$quote($aliases[$legacyValue]->value)}";
            }
        }

        return 'CASE '.implode(' ', $branches).' END';
    }

    /**
     * @return array<int, string>
     */
    public static function blogCategoryValues(?ShopTypeEnum $shopType = null): array
    {
        return array_map(fn (self $subType) => $subType->value, self::blogCategories($shopType));
    }

    /**
     * @return array<int, string>
     */
    public static function allBlogCategoryValues(): array
    {
        return array_map(fn (self $subType) => $subType->value, self::allBlogCategories());
    }

    public static function catalogueLabels(): array
    {
        return [
            self::PRODUCT->value           => 'Product',
            self::FAMILY->value            => 'Family',
            self::DEPARTMENT->value        => 'Department',
            self::SUB_DEPARTMENT->value    => 'Sub Department',
            self::COLLECTION->value        => 'Collection',
        ];
    }

    public static function catalogueCount(): array
    {
        return [
            self::PRODUCT->value           => null,
            self::FAMILY->value            => null,
            self::DEPARTMENT->value        => null,
            self::SUB_DEPARTMENT->value    => null,
            self::COLLECTION->value        => null,
        ];
    }
}
