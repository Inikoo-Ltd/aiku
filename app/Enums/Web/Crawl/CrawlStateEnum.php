<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 12:03:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;
use App\Models\Web\Website;

enum CrawlStateEnum: string
{
    use EnumHelperTrait;

    case READY = 'ready';
    case RUNNING = 'running';
    case FINISH = 'finish';

    public static function labels(): array
    {
        return [
            'ready'   => __('Ready'),
            'running' => __('Running'),
            'finish'  => __('Finished'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'ready'   => [
                'tooltip' => __('Ready'),
                'icon'    => 'fal fa-hourglass-start',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
            ],
            'running' => [
                'tooltip' => __('Running'),
                'icon'    => 'fal fa-spinner',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
            ],
            'finish'  => [
                'tooltip' => __('Finished'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
            ],
        ];
    }

    public static function count(Website $website): array
    {
        return [
            'ready'   => $website->crawls()->where('state', self::READY->value)->count(),
            'running' => $website->crawls()->where('state', self::RUNNING->value)->count(),
            'finish'  => $website->crawls()->where('state', self::FINISH->value)->count(),
        ];
    }
}
