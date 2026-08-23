<?php

namespace App\Actions\Web\Website\LlmsTxt;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteLlmsTxt;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The core text is always generated from website data so access rules (register to
 * see prices, no public ordering API) can never be replaced by an upload; uploaded,
 * organisation or group text is appended as additional information only.
 */
class GetLlmsTxt
{
    use AsAction;

    public function handle(Website $website): string
    {
        $content = $this->getCoreContent($website);

        $extra = $this->getExtraContent($website);
        if ($extra) {
            $content .= "\n## Additional information\n\n".trim($extra)."\n";
        }

        return $content;
    }

    protected function getExtraContent(Website $website): ?string
    {
        $llmsTxt = WebsiteLlmsTxt::getActiveForWebsite($website);
        if ($llmsTxt && $llmsTxt->content) {
            return $llmsTxt->content;
        }

        return $website->organisation->settings['llms_txt']['content']
            ?? $website->group->settings['llms_txt']['content']
            ?? null;
    }

    protected function getCoreContent(Website $website): string
    {
        $shop      = $website->shop;
        $url       = 'https://www.'.$website->domain;
        $showPrice = (bool) Arr::get($website->settings, 'webpage.show_price', false);

        $audience = match ($shop?->type) {
            ShopTypeEnum::B2B => 'a wholesale (B2B) shop for registered trade customers',
            ShopTypeEnum::B2C => 'a retail (B2C) shop',
            ShopTypeEnum::DROPSHIPPING => 'a dropshipping platform for registered resellers',
            ShopTypeEnum::FULFILMENT => 'a fulfilment and storage services portal for registered customers',
            default => 'a website',
        };

        $lines = [
            "# {$website->name}",
            '',
            "> {$website->name} ({$website->domain}) is {$audience}.".($shop ? " Currency: {$shop->currency->code}." : ''),
            '',
            '## Access',
            '',
            $showPrice
                ? "Prices are public. Placing an order requires a customer account: register at {$url}/app/register, log in at {$url}/app/login."
                : "Prices and stock availability are only shown to logged-in customers. To see prices or place an order, register first at {$url}/app/register and log in at {$url}/app/login. Do not guess or infer prices for this shop.",
            "There is no public ordering API. Orders are placed by logged-in customers through the website basket and checkout; an agent acting for a customer must use that customer's own account.",
            '',
            '## Links',
            '',
            "- [Home]({$url})",
            "- [Sitemap]({$url}/sitemap.xml): index of products, departments, sub-departments, families, collections, blogs and pages",
            "- [robots.txt]({$url}/robots.txt)",
        ];

        if (in_array($shop?->type, [ShopTypeEnum::B2B, ShopTypeEnum::B2C, ShopTypeEnum::DROPSHIPPING])) {
            $lines = array_merge($lines, [
                '',
                '## Catalogue URLs',
                '',
                "- Departments: {$url}/catalogue/department/{slug}",
                "- Sub-departments: {$url}/catalogue/sub-department/{slug}",
                "- Families: {$url}/catalogue/family/{slug}",
                "- Collections: {$url}/catalogue/collection/{slug}",
                "- Products: {$url}/catalogue/products/{slug}",
                '',
                'Catalogue pages carry schema.org JSON-LD (Product, Offer, ItemList, BreadcrumbList); prefer it over scraping markup. Offers omit price when the visitor is not logged in.',
            ]);
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
