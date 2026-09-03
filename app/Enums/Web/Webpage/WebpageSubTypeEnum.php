<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 01:32:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;

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

    /** Legacy catch all still stored on existing webpages, resolved by resolveBlogCategory. */
    case BLOG = 'blog';
    case NEWSLETTERS    = 'newsletters';
    case PRODUCT_GUIDES = 'product_guides';
    case BUSINESS_TIPS  = 'business_tips';

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
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function blogCategories(): array
    {
        return [
         /*    self::BLOG, */
            self::NEWSLETTERS,
            self::PRODUCT_GUIDES,
            self::BUSINESS_TIPS,
        ];
    }

    public static function blogCategoriesWithLabel(): array
    {
        return [
           /*  [
                'value' => self::BLOG->value,
                'label' => __("Blog"),
            ], */
            [
                'value'       => self::NEWSLETTERS->value,
                'label'       => __('Newsletters'),
            ],
            [
                'value' => self::PRODUCT_GUIDES->value,
                'label' => __("Product Guides"),
            ],
            [
                'value' => self::BUSINESS_TIPS->value,
                'label' => __("Business Tips"),
            ],
        ];
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
     * category on their own; they are read as the alias they are mapped to.
     *
     * @return array<int, string>
     */
    public static function ambiguousBlogSubTypes(): array
    {
        return ['blog'];
    }

    /**
     * Resolves the blog category of a webpage. A catch all sub type does not identify a category on
     * its own and is read as its alias, unless $withAmbiguousFallback is disabled, which callers
     * persisting the result use to leave undecidable webpages alone.
     */
    public static function resolveBlogCategory(?string $subType, bool $withAmbiguousFallback = true): ?self
    {
        if ($subType === null) {
            return null;
        }

        $aliases = self::legacyBlogCategoryAliases();

        if (in_array($subType, self::ambiguousBlogSubTypes(), true)) {
            return $withAmbiguousFallback ? ($aliases[$subType] ?? null) : null;
        }

        $category = self::tryFrom($subType);

        if ($category && in_array($category, self::blogCategories(), true)) {
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

        foreach (self::blogCategories() as $category) {
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
    public static function blogCategoryValues(): array
    {
        return array_map(fn (self $subType) => $subType->value, self::blogCategories());
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
