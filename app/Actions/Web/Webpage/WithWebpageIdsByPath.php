<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;

trait WithWebpageIdsByPath
{
    /**
     * @return array<string, int>
     */
    protected function webpageIdsByPath(Website $website): array
    {
        $webpageIdsByPath = [];

        $webpages = Webpage::where('website_id', $website->id)
            ->orderByRaw('CASE WHEN state = ? THEN 1 ELSE 0 END', [WebpageStateEnum::LIVE->value])
            ->get(['id', 'url', 'canonical_url']);

        foreach ($webpages as $webpage) {
            $webpageIdsByPath[$this->normaliseWebpagePath('/'.ltrim((string) $webpage->url, '/'))] = $webpage->id;

            if ($webpage->canonical_url) {
                $webpageIdsByPath[$this->normaliseWebpagePath(parse_url($webpage->canonical_url, PHP_URL_PATH) ?: '/')] = $webpage->id;
            }
        }

        return $webpageIdsByPath;
    }

    protected function matchWebpageId(string $url, array $webpageIdsByPath): ?int
    {
        return $webpageIdsByPath[$this->normaliseWebpagePath(parse_url($url, PHP_URL_PATH) ?: '/')] ?? null;
    }

    private function normaliseWebpagePath(string $path): string
    {
        $path = rawurldecode($path);

        return $path === '/' ? $path : rtrim($path, '/');
    }
}
