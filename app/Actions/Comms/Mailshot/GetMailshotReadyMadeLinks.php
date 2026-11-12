<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotReadyMadeLinkEnum;
use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Comms\Mailshot;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMailshotReadyMadeLinks
{
    use AsObject;

    /**
     * @return array<int, array{key: string, label: string, url: string, host: string, path: string, anchor: string, utm: array<string, string>}>
     */
    public function handle(Mailshot $mailshot): array
    {
        $shop    = $mailshot->shop;
        $website = $shop?->website;

        if (!$website) {
            return [];
        }

        $baseUrl = $this->getWebsiteBaseUrl($website);
        $host    = (string)parse_url($baseUrl, PHP_URL_HOST);

        return collect(MailshotReadyMadeLinkEnum::cases())
            ->filter(fn (MailshotReadyMadeLinkEnum $link) => $link->isAvailableIn($shop))
            ->map(fn (MailshotReadyMadeLinkEnum $link) => [
                'key'    => $link->value,
                'label'  => $link->label(),
                'url'    => $baseUrl.$link->path().'#'.$link->anchor(),
                'host'   => $host,
                'path'   => $link->path(),
                'anchor' => $link->anchor(),
                'utm'    => [MailshotUtmParameterEnum::CAMPAIGN->value => $link->campaign()],
            ])
            ->values()
            ->all();
    }

    public function getWebsiteBaseUrl(Website $website): string
    {
        if (!app()->environment('production')) {
            return rtrim($website->getUrl(), '/');
        }

        $canonicalUrl = $website->webpages()
            ->where('state', WebpageStateEnum::LIVE)
            ->whereNotNull('canonical_url')
            ->value('canonical_url');

        $host = $canonicalUrl ? parse_url($canonicalUrl, PHP_URL_HOST) : null;

        return 'https://'.($host ?: $website->domain);
    }
}
