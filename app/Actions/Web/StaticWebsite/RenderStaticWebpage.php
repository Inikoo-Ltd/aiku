<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Puts the site's shared parts back where a page holds their markers. A marker without a part is left
 * as the HTML comment it is, which verification reports.
 */
class RenderStaticWebpage
{
    use AsAction;

    /**
     * @param array<string, string|null> $componentOverrides code => html to show instead of the saved one (null: as if the part did not exist)
     */
    public function handle(StaticWebsite $staticWebsite, string $html, array $componentOverrides = []): string
    {
        $codes = StaticWebsiteComponent::codesIn($html);
        if (!$codes) {
            return $html;
        }

        $components = $staticWebsite->components()->whereIn('code', $codes)->toBase()->pluck('html', 'code')->all();
        foreach ($componentOverrides as $code => $override) {
            if ($override === null) {
                unset($components[$code]);
            } else {
                $components[$code] = $override;
            }
        }

        return preg_replace_callback(
            '/<!--aiku:component:('.StaticWebsiteComponent::CODE_PATTERN.')-->/',
            fn (array $match) => $components[$match[1]] ?? $match[0],
            $html
        );
    }
}
