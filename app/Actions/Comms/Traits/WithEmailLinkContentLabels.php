<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Traits;

use Illuminate\Support\Str;

trait WithEmailLinkContentLabels
{
    protected const ANCHOR_PATTERN = '/<a\s[^>]*>.*?<\/a>/is';

    protected const TRACKING_REDIRECTS = [
        'awstrack.me',
        'list-manage.com',
        'sendgrid.net',
    ];

    protected function getAnchorUrl(string $anchor): ?string
    {
        if (!preg_match('/href=(["\'])(.*?)\1/i', $anchor, $matches)) {
            return null;
        }

        $url = trim(html_entity_decode($matches[2], ENT_QUOTES));

        return Str::startsWith(Str::lower($url), ['http://', 'https://']) ? $url : null;
    }

    protected function getContentLabel(string $anchor, array &$counters): string
    {
        $anchor = Str::lower($anchor);

        $kind = match (true) {
            str_contains($anchor, '<img')        => 'image',
            str_contains($anchor, 'v:roundrect') => 'button',
            default                              => 'text',
        };

        $position = $counters[$kind] = ($counters[$kind] ?? 0) + 1;

        if ($position === 1 && $kind !== 'text') {
            return 'hero_'.$kind;
        }

        return $kind.'_'.$position;
    }

    protected function isTrackingRedirect(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return Str::contains($host, self::TRACKING_REDIRECTS);
    }
}
