<?php

namespace App\Enums\Web\WebsiteDialog;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebsiteDialogTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SNAPSHOTS = 'snapshots';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteDialogTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt-fast',
                'type'  => 'icon',
            ],
            WebsiteDialogTabsEnum::SNAPSHOTS => [
                'title' => __('Snapshots'),
                'icon'  => 'fal fa-layer-group',
                'type'  => 'icon',
            ],
        };
    }

    public static function labels(): array
    {
        return [
            'showcase'  => __('Showcase'),
            'snapshots' => __('Snapshots')
        ];
    }
}
