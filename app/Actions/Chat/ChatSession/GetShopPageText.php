<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 01:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The words on a shop's live policy pages (returns, delivery, terms), as customers read them on
 * the website, for a model to answer from and for code to check its quote against.
 */
class GetShopPageText
{
    use AsAction;

    private const int MAX_CHARS = 15000;

    /**
     * @param  array<int, WebpageSubTypeEnum>  $subTypes
     * @return array<int, array{title: string, url: string, text: string}>
     */
    public function handle(Shop $shop, array $subTypes): array
    {
        $website = $shop->website;

        if (!$website) {
            return [];
        }

        return Webpage::where('website_id', $website->id)
            ->whereIn('sub_type', $subTypes)
            ->where('state', WebpageStateEnum::LIVE)
            ->get()
            ->sortBy(fn (Webpage $webpage) => array_search($webpage->sub_type, $subTypes, true))
            ->map(fn (Webpage $webpage) => [
                'title' => (string) $webpage->title,
                'url'   => $webpage->canonical_url ?: 'https://'.ltrim($website->domain, '/').'/'.$webpage->url,
                'text'  => mb_substr(self::text($webpage->published_layout ?? []), 0, self::MAX_CHARS),
            ])
            ->filter(fn (array $page) => mb_strlen($page['text']) > 50)
            ->values()
            ->all();
    }

    /**
     * Every piece of HTML in the page's blocks, as plain text with one line per paragraph.
     *
     * @param  array<mixed>  $layout
     */
    public static function text(array $layout): string
    {
        $html = [];

        array_walk_recursive($layout, function ($value) use (&$html) {
            if (is_string($value) && str_contains($value, '<')) {
                $html[] = $value;
            }
        });

        $text = html_entity_decode(strip_tags(preg_replace('/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/i', "\n", implode("\n", $html))), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace(['/[ \t\x{00A0}]+/u', '/\n\s*\n+/'], [' ', "\n"], $text));
    }

    public static function normalised(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }
}
