<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;
use App\Models\Web\StaticWebsite;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Puts the site's shared components back where a page holds their markers. A marker without a
 * component is left as the HTML comment it is, which verification reports.
 */
class RenderStaticWebpage
{
    use AsAction;

    /**
     * @param array<string, string> $componentOverrides code => html, to show a page with an earlier version of a shared part
     */
    public function handle(StaticWebsite $staticWebsite, string $html, array $componentOverrides = []): string
    {
        if (!str_contains($html, '<!--aiku:component:')) {
            return $html;
        }

        $components = $staticWebsite->components()->toBase()->pluck('html', 'code')->merge($componentOverrides);

        $replacements = [];
        foreach (StaticWebsiteComponentEnum::cases() as $component) {
            if ($components->has($component->value)) {
                $replacements[$component->marker()] = $components->get($component->value);
            }
        }

        return strtr($html, $replacements);
    }
}
