<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Web\StaticWebsite;

use App\Enums\EnumHelperTrait;
use App\Models\Web\StaticWebsiteComponent;

/**
 * The parts the import takes out of every page of an info website. Each is the content of one element, found by the
 * attribute that identifies it; the element's own opening tag stays on the page, because its colour
 * classes follow the page's first section.
 */
enum StaticWebsiteComponentEnum: string
{
    use EnumHelperTrait;

    case HEADER = 'header';
    case FOOTER = 'footer';

    public function tag(): string
    {
        return match ($this) {
            self::HEADER => 'header',
            self::FOOTER => 'footer',
        };
    }

    public function identifyingAttribute(): string
    {
        return match ($this) {
            self::HEADER => 'id="header"',
            self::FOOTER => 'id="footer-sections"',
        };
    }

    public function marker(): string
    {
        return StaticWebsiteComponent::marker($this->value);
    }
}
