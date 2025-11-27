<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProspectsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PROSPECTS = 'prospects';
    case OPT_IN    = 'opt_in';
    case OPT_OUT   = 'opt_out';
    case CONTACTED = 'contacted';
    case FAILED    = 'failed';
    case SUCCESS   = 'success';
    case HISTORY   = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            self::PROSPECTS => [
                'title' => __('Prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            self::OPT_IN => [
                'title' => __('Opted in prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            self::OPT_OUT => [
                'title' => __('Opted out prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            self::CONTACTED => [
                'title' => __('Contacted prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            self::FAILED => [
                'title' => __('Fail prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            self::SUCCESS => [
                'title' => __('Success prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            self::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ]
        };
    }
}
