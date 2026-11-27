<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\Product\FillShopProductTranslations;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Who uses each language of a shop: web users, customers, their invoices and sales, how ready the texts are,
 * and the shop's open channels per platform and language. A customer's language is its root web user's, else the
 * shop's; a channel's is its own only while the shop lets customers choose channel languages, else the shop's.
 * Products in channels are the hydrated portfolio counts, never a scan of portfolios.
 */
class GetShopLanguageReach
{
    use AsAction;

    public const int CACHE_SECONDS = 600;

    /**
     * @return array{
     *     currency_code: string,
     *     channel_languages: bool,
     *     languages: list<array<string, mixed>>,
     *     channels: array{by_platform: list<array{type: string, label: string, channels: int, products: int}>, by_language: list<array{code: string, language: string, channels: int, products: int}>, matrix: list<array{type: string, code: string, channels: int, products: int}>}
     * }
     */
    public function handle(Shop $shop, bool $useCache = true): array
    {
        if (!$useCache) {
            return $this->compute($shop);
        }

        return Cache::tags(["dashboard-shop-$shop->id"])->remember("shop_language_reach:$shop->id", self::CACHE_SECONDS, fn () => $this->compute($shop));
    }

    private function compute(Shop $shop): array
    {
        $mainLanguageId = $shop->language_id;
        $enabled        = $shop->enabledLanguageCodes();
        $preview        = $shop->previewLanguageCodes();
        $since30        = now()->subDays(30);
        $since12m       = now()->subYear();

        $webUsers = collect(DB::select(
            'select language_id, count(*) as total, count(*) filter (where created_at >= ?) as new_30d from web_users where shop_id = ? and deleted_at is null group by language_id',
            [$since30, $shop->id]
        ))->keyBy('language_id');

        $customers = collect(DB::select(<<<'SQL'
            with customer_languages as (
                select c.id, c.created_at, coalesce(wu.language_id, ?) as language_id
                from customers c
                left join lateral (
                    select language_id from web_users
                    where web_users.customer_id = c.id and web_users.is_root and web_users.deleted_at is null
                    order by web_users.id limit 1
                ) wu on true
                where c.shop_id = ? and c.deleted_at is null
            ), sales as (
                select i.customer_id,
                       count(*) filter (where i.date >= ?) as invoices_30d,
                       coalesce(sum(i.net_amount) filter (where i.date >= ?), 0) as sales_30d,
                       count(*) as invoices_12m,
                       coalesce(sum(i.net_amount), 0) as sales_12m
                from invoices i
                where i.shop_id = ? and i.type = ? and i.in_process = false and i.deleted_at is null and i.date >= ?
                group by i.customer_id
            )
            select cl.language_id,
                   count(*) as total,
                   count(*) filter (where cl.created_at >= ?) as new_30d,
                   coalesce(sum(s.invoices_30d), 0) as invoices_30d,
                   coalesce(sum(s.sales_30d), 0) as sales_30d,
                   coalesce(sum(s.invoices_12m), 0) as invoices_12m,
                   coalesce(sum(s.sales_12m), 0) as sales_12m
            from customer_languages cl
            left join sales s on s.customer_id = cl.id
            group by cl.language_id
            SQL, [$mainLanguageId, $shop->id, $since30, $since30, $shop->id, InvoiceTypeEnum::INVOICE->value, $since12m, $since30]))->keyBy('language_id');

        $channelRows = collect(DB::select(
            'select p.type, coalesce('.($shop->customersChooseChannelLanguage() ? 'csc.language_id' : 'null').', ?) as language_id, count(*) as channels, coalesce(sum(csc.number_portfolios), 0) as products
             from customer_sales_channels csc join platforms p on p.id = csc.platform_id
             where csc.shop_id = ? and csc.status = ? and csc.deleted_at is null
             group by 1, 2',
            [$mainLanguageId, $shop->id, CustomerSalesChannelStatusEnum::OPEN->value]
        ));

        $languageIds = array_unique(array_merge($webUsers->keys()->all(), $customers->keys()->all(), $channelRows->pluck('language_id')->all()));
        $languages   = Language::whereIn('code', array_merge($enabled, $preview))->orWhereIn('id', $languageIds)->get(['id', 'code', 'name'])->keyBy('id');
        $codeById    = $languages->pluck('code', 'id');

        $readiness = collect(GetShopLanguageReadiness::run($shop))->keyBy('code');
        $problems  = $this->translationProblems($shop);

        $customersRoute = fn (string $code) => [
            'name'       => 'grp.org.shops.show.crm.customers.index',
            'parameters' => ['organisation' => $shop->organisation->slug, 'shop' => $shop->slug, 'filter[language]' => $code],
        ];

        $rows = $languages->map(function (Language $language) use ($shop, $enabled, $preview, $webUsers, $customers, $readiness, $problems, $customersRoute) {
            $webUser  = $webUsers->get($language->id);
            $customer = $customers->get($language->id);
            $ready    = $readiness->get($language->code);

            return [
                'code'              => $language->code,
                'language'          => $language->name,
                'is_main'           => $language->id === $shop->language_id,
                'is_preview'        => in_array($language->code, $preview, true),
                'is_offered'        => in_array($language->code, $enabled, true) || in_array($language->code, $preview, true),
                'web_users'         => (int) ($webUser->total ?? 0),
                'web_users_new_30d' => (int) ($webUser->new_30d ?? 0),
                'customers'         => (int) ($customer->total ?? 0),
                'customers_new_30d' => (int) ($customer->new_30d ?? 0),
                'invoices_30d'      => (int) ($customer->invoices_30d ?? 0),
                'sales_30d'         => (float) ($customer->sales_30d ?? 0),
                'invoices_12m'      => (int) ($customer->invoices_12m ?? 0),
                'sales_12m'         => (float) ($customer->sales_12m ?? 0),
                'readiness'         => [
                    'products'              => (int) ($ready['products'] ?? $readiness->first()['products'] ?? 0),
                    'names'                 => (int) ($ready['names'] ?? 0),
                    'descriptions'          => (int) ($ready['descriptions'] ?? 0),
                    'categories'            => (int) ($ready['categories'] ?? $readiness->first()['categories'] ?? 0),
                    'category_names'        => (int) ($ready['category_names'] ?? 0),
                    'category_descriptions' => (int) ($ready['category_descriptions'] ?? 0),
                    'failed'                => (int) ($problems[$language->code]->failed ?? 0),
                    'to_clean'              => (int) ($problems[$language->code]->to_clean ?? 0),
                ],
                'customers_route'   => $customersRoute($language->code),
            ];
        })
            ->filter(fn (array $row) => $row['is_offered'] || $row['customers'] > 0 || $row['web_users'] > 0)
            ->sortBy([['is_main', 'desc'], ['is_offered', 'desc'], ['is_preview', 'asc'], ['customers', 'desc'], ['code', 'asc']])
            ->values()
            ->all();

        $label = fn (string $type) => PlatformTypeEnum::tryFrom($type)?->labels()[$type] ?? $type;
        $named = fn (int $languageId) => $languages->get($languageId);

        return [
            'currency_code'     => $shop->currency->code,
            'channel_languages' => $shop->customersChooseChannelLanguage(),
            'languages'         => $rows,
            'channels'          => [
                'by_platform' => $channelRows->groupBy('type')
                    ->map(fn ($group, $type) => ['type' => $type, 'label' => $label($type), 'channels' => (int) $group->sum('channels'), 'products' => (int) $group->sum('products')])
                    ->sortByDesc('channels')->values()->all(),
                'by_language' => $channelRows->groupBy('language_id')
                    ->map(fn ($group, $languageId) => ['code' => $codeById[$languageId] ?? (string) $languageId, 'language' => $named($languageId)?->name ?? (string) $languageId, 'channels' => (int) $group->sum('channels'), 'products' => (int) $group->sum('products')])
                    ->sortByDesc('channels')->values()->all(),
                'matrix'      => $channelRows
                    ->map(fn ($row) => ['type' => $row->type, 'code' => $codeById[$row->language_id] ?? (string) $row->language_id, 'channels' => (int) $row->channels, 'products' => (int) $row->products])
                    ->values()->all(),
            ],
        ];
    }

    /**
     * Active products whose last fill in a language was rejected (failed) or whose copied text the cleaner could not clean (to_clean).
     *
     * @return array<string, object{failed: int, to_clean: int}>
     */
    private function translationProblems(Shop $shop): array
    {
        return collect(DB::select(
            "select l.locale,
                    count(*) filter (where p.data->'translation_sources'->l.locale->>'failed' is not null) as failed,
                    count(*) filter (where p.data->'translation_sources'->l.locale->>? is not null) as to_clean
             from products p
             cross join lateral jsonb_object_keys(case when jsonb_typeof(p.data->'translation_sources') = 'object' then p.data->'translation_sources' else '{}' end) l(locale)
             where p.shop_id = ? and p.state = ? and p.deleted_at is null
             group by l.locale",
            [FillShopProductTranslations::SHOP_TEXT_FAILED, $shop->id, ProductStateEnum::ACTIVE->value]
        ))->keyBy('locale')->all();
    }
}
