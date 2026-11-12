<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Web\StaticWebsite\StoreStaticWebsite;
use App\Models\Web\StaticWebsite;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Info website tools are for group administrators only for now; group webmasters are to follow by
 * widening User::can_use_mcp_info_websites.
 */
abstract class AikuInfoWebsiteTool extends Tool
{
    use WithMcpPermissions;

    protected function denied(Request $request): ?Response
    {
        if ($request->user()?->can_use_mcp_info_websites) {
            return null;
        }

        return Response::error('Info websites can only be changed by group administrators for now. Do not retry; nothing was changed.');
    }

    protected function staticWebsite(Request $request): StaticWebsite|Response
    {
        $identifier = strtolower(trim((string) $request->string('website')));
        $domain     = StoreStaticWebsite::normaliseDomain($identifier);

        $staticWebsite = StaticWebsite::where('group_id', $request->user()->group_id)
            ->where(fn ($query) => $query->where('domain', $domain)->orWhere('slug', $identifier))
            ->first();

        if ($staticWebsite) {
            return $staticWebsite;
        }

        $known = StaticWebsite::where('group_id', $request->user()->group_id)->orderBy('domain')->pluck('domain')->implode(', ');

        return Response::error("'{$request->string('website')}' is not an info website. Info websites: $known.");
    }

    protected function liveUrl(StaticWebsite $staticWebsite, string $path = '/'): string
    {
        return 'https://www.'.$staticWebsite->domain.$path;
    }

    protected function previewUrl(StaticWebsite $staticWebsite, string $path = '/'): string
    {
        return route('grp.marketing.info_websites.preview', [$staticWebsite->slug, ltrim($path, '/')]);
    }

    protected function kilobytes(string $html): int
    {
        return (int) round(strlen($html) / 1024);
    }

    protected function shortened(string $text, int $limit): string
    {
        return Str::limit($text, $limit);
    }
}
