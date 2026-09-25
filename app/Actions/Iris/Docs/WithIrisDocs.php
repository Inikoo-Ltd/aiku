<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Docs;

use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Actions\UI\AikuPublic\BlogPosts;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Web\Website;
use Illuminate\Support\Collection;
use Locale;
use Symfony\Component\HttpFoundation\Response;

trait WithIrisDocs
{
    use WithOrderForbiddenCountryCheck;

    public const string PATH = 'docs';

    protected function ensureDropshippingWebsite(Website $website): void
    {
        abort_unless($website->shop->type === ShopTypeEnum::DROPSHIPPING, 404);
    }

    /**
     * @param  array{shops:array<int,string>}  $doc
     */
    protected function isForWebsite(array $doc, Website $website): bool
    {
        return !$doc['shops'] || in_array($website->shop->slug, $doc['shops'], true);
    }

    protected function language(Website $website): string
    {
        return strtolower($website->shop->language->code ?? 'en');
    }

    protected function everything(): Collection
    {
        return BlogPosts::everything(BlogPosts::DROPSHIPPING_DOCS);
    }

    /**
     * @param  array{base_slug:string}  $englishDoc
     * @return array<string, mixed>
     */
    protected function inLanguage(array $englishDoc, string $language, Collection $everything): array
    {
        if ($language === 'en') {
            return $englishDoc;
        }

        return $everything->first(fn (array $doc) => $doc['base_slug'] === $englishDoc['base_slug'] && $doc['lang'] === $language) ?? $englishDoc;
    }

    /**
     * @param  array{slug:string,title:string,summary:string,category:?string,series:?string,series_order:int,lang:string}  $doc
     * @return array<string, mixed>
     */
    protected function summary(array $doc, Website $website): array
    {
        return [
            'slug'     => $doc['slug'],
            'title'    => $this->fillPlaceholders($doc['title'], $website),
            'summary'  => $this->fillPlaceholders($doc['summary'], $website),
            'category' => $doc['category'],
            'series'   => $doc['series'],
            'order'    => $doc['series_order'],
            'lang'     => $doc['lang'],
            'url'      => '/'.self::PATH.'/'.$doc['slug'],
        ];
    }

    public function fillPlaceholders(string $text, Website $website, bool $isHtml = false): string
    {
        $shop = $website->shop;

        $values = [
            '{shop_name}'    => $shop->name,
            '{company_name}' => $shop->company_name ?: $shop->name,
            '{shop_address}' => preg_replace('/\s*\n\s*/', ', ', trim($shop->address?->formatted_address ?? '')),
        ];

        $values = $isHtml ? array_map('e', $values) : $values;
        if ($isHtml && str_contains($text, '{order_charges}')) {
            $values['{order_charges}'] = $this->orderChargesHtml($website);
        }
        if ($isHtml && str_contains($text, '{blocked_delivery_countries}')) {
            $values['{blocked_delivery_countries}'] = $this->blockedDeliveryCountriesHtml($website);
        }

        return strtr($text, $values);
    }

    private function blockedDeliveryCountriesHtml(Website $website): string
    {
        $language = $this->language($website);

        $countries = collect($this->getBannedCountriesTarget($website->shop)->bannedDeliveryCountries())
            ->map(fn (array $ban, string $code) => e(Locale::getDisplayRegion('-'.$code, $language) ?: $code)
                .(data_get($ban, 'postcode') ? ' ('.e(__('some postcodes only')).')' : ''))
            ->sort()
            ->values();

        return $countries->isEmpty()
            ? '<p>'.e(__('None')).'</p>'
            : '<ul>'.$countries->map(fn (string $country) => '<li>'.$country.'</li>')->implode('').'</ul>';
    }

    private function orderChargesHtml(Website $website): string
    {
        $shop = $website->shop;

        $items = $shop->charges()
            ->where('state', ChargeStateEnum::ACTIVE)
            ->whereIn('type', [ChargeTypeEnum::COLLECTION, ChargeTypeEnum::HANGING, ChargeTypeEnum::PREMIUM, ChargeTypeEnum::PACKING])
            ->orderBy('id')
            ->get()
            ->filter(fn (Charge $charge) => (float) data_get($charge->settings, 'amount') > 0)
            ->map(function (Charge $charge) use ($shop) {
                $amount = str_replace('.00', '', number_format((float) data_get($charge->settings, 'amount'), 2));
                $name   = $charge->type === ChargeTypeEnum::HANGING->value ? __('Small order charge') : $charge->name;

                return '<li><b>'.e($name).'</b>: '.e($shop->currency->symbol.$amount)
                    .($charge->description && $charge->description !== $charge->name ? '. '.e($charge->description) : '').'</li>';
            });

        return $items->isEmpty() ? '' : '<ul>'.$items->implode('').'</ul>';
    }

    protected function cacheable(Response $response, Website $website): Response
    {
        $response->headers->set('Cache-Control', 'public, s-maxage=300, max-age=0');
        $response->headers->set('X-Aiku-Cacheable-Inertia', '1');
        $response->headers->set('X-Is-Diff', 'N');
        $response->headers->set('X-AIKU-WEBSITE', (string) $website->id);

        return $response;
    }
}
