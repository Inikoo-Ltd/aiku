<?php

namespace App\Enums\UI\Web\Redirect;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RedirectTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case REDIRECTS = 'redirects';

    public function blueprint(): array
    {
        return match ($this) {
            RedirectTabsEnum::REDIRECTS => [
                'title' => __('Redirects'),
                'icon'  => 'fal fa-terminal',
            ],
        };
    }

}
