<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;

trait WithStaticWebsiteHtml
{
    /**
     * Where a component's element sits in a page: the start of its opening tag, the end of that tag
     * and the start of its closing tag, counting nested elements of the same name.
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    protected function findComponentElement(string $html, StaticWebsiteComponentEnum $component): ?array
    {
        return $this->findElement($html, $component->tag(), $component->identifyingAttribute());
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    protected function findElement(string $html, string $tag, string $identifyingAttribute): ?array
    {
        $attributeAt = strpos($html, $identifyingAttribute);
        if ($attributeAt === false) {
            return null;
        }

        $openStart = strrpos(substr($html, 0, $attributeAt), '<'.$tag);
        $openEnd   = strpos($html, '>', $attributeAt);
        if ($openStart === false || $openEnd === false) {
            return null;
        }
        $openEnd++;

        preg_match_all('#<(/?)'.$tag.'(?=[\s>/])#i', $html, $matches, PREG_OFFSET_CAPTURE, $openEnd);

        $depth = 1;
        foreach ($matches[1] as $index => [$slash]) {
            $depth += $slash === '/' ? -1 : 1;
            if ($depth === 0) {
                return [$openStart, $openEnd, $matches[0][$index][1]];
            }
        }

        return null;
    }

    /**
     * Removes what the old host baked in for the page it rendered: the current menu item, the skip
     * link target and the login link's return page. The runtime marks the current menu item again.
     */
    protected function withoutPageSpecificState(string $html): string
    {
        $html = preg_replace('/\s(header-nav-item--active|header-menu-nav-item--active|header-nav-folder-item--active)(?=[\s"])/', '', $html);
        $html = preg_replace('/\saria-current="[^"]*"/', '', $html);
        $html = preg_replace('/href="[^"]*#page"(\s+class="header-skip-link)/', 'href="#page"$1', $html);

        return preg_replace('/(class="user-accounts-text-link[^"]*"\s+href=")[^"]*/', '$1/', $html);
    }

    /**
     * What two copies of a shared part must agree on once the old host's render noise is gone:
     * animation classes and timings, its script settings and the size the runtime recomputes.
     */
    protected function comparableComponentHtml(string $html): string
    {
        $html = $this->withoutPageSpecificState($html);
        $html = preg_replace('/\s(preFade|fadeIn|preScale|scaleIn|preSlide|slideIn|preClip|clipIn|preFlex|flexIn)(?=[\s"])/', '', $html);
        $html = preg_replace('/\sdata-current-(?:styles|context)="[^"]*"/', '', $html);
        $html = preg_replace('/(<span class="sqsrte-scaled-text") style="font-size:\s*[\d.]+px;?"/', '$1', $html);

        return preg_replace_callback('/ style=(["\'])(.*?)\1/s', function (array $match) {
            $style = trim(preg_replace('/\s*transition-(?:timing-function|duration|delay)\s*:[^;"\']*;?/', '', $match[2]));

            return $style === '' ? '' : ' style='.$match[1].$style.$match[1];
        }, $html);
    }
}
