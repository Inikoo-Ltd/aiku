<?php

/*
 * Author: ekayudinata <ekayudinatha@gmail.com>
 * Created: Tue, 27 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

namespace App\Services\SiteAudit;

use App\Actions\Web\Crawl\AuditWebsite;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The robots.txt check of "Crawl page" on a family or product webpage: the page passes when no
 * Disallow of the User-agent: * group, the rules every search engine and AI crawler reads, blocks
 * its path. Groups naming one bot (Semrush's SiteAuditBot, Amazonbot) do not fail the page.
 */
class CatalogueRobotsTxt
{
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        try {
            $response = Http::withHeaders(['User-Agent' => AuditWebsite::USER_AGENT])
                ->when(!app()->isProduction(), fn ($request) => $request->withoutVerifying())
                ->connectTimeout(10)
                ->timeout(20)
                ->get($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/robots.txt');
        } catch (Throwable) {
            return true;
        }

        if (!$response->successful()) {
            return true;
        }

        return RobotsTxt::parse($response->body(), '*')->isAllowed($parts['path'] ?? '/');
    }
}
