<?php

namespace App\Actions\Web\Website\LlmsTxt;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteLlmsTxt;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLlmsTxt
{
    use AsAction;

    public function handle(Website $website): ?string
    {
        $llmsTxt = WebsiteLlmsTxt::getActiveForWebsite($website);

        if ($llmsTxt && $llmsTxt->content) {
            return $llmsTxt->content;
        }

        if ($llmsTxt && !$llmsTxt->use_fallback) {
            return null;
        }

        return $this->getGlobalFallback($website);
    }

    protected function getGlobalFallback(Website $website): ?string
    {
        $orgFallback = $website->organisation->settings['llms_txt']['content'] ?? null;
        if ($orgFallback) {
            return $orgFallback;
        }

        $groupFallback = $website->group->settings['llms_txt']['content'] ?? null;
        if ($groupFallback) {
            return $groupFallback;
        }

        return $this->getDefaultContent($website);
    }

    protected function getDefaultContent(Website $website): string
    {
        $url = 'https://'.$website->domain;

        $shopTypeDescription = match ($website->shop?->type) {
            ShopTypeEnum::B2B => 'This is a B2B (wholesale) ecommerce website; purchases require a registered trade account.',
            ShopTypeEnum::B2C => 'This is a B2C (retail) ecommerce website selling directly to consumers.',
            ShopTypeEnum::DROPSHIPPING => 'This is a dropshipping ecommerce website; orders are shipped directly to end customers on behalf of resellers.',
            ShopTypeEnum::FULFILMENT => 'This is a fulfilment services website offering warehousing and order fulfilment.',
            default => null,
        };

        return "# {$website->name}\n\n"
            ."> {$website->name} ({$website->domain})".($shopTypeDescription ? ' '.$shopTypeDescription : '')."\n\n"
            ."## Links\n\n"
            ."- [Home]({$url}): Homepage\n"
            ."- [Sitemap]({$url}/sitemap.xml): Full list of pages\n";
    }
}
