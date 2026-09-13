<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketQaStatusEnum: string
{
    use EnumHelperTrait;

    case REQUESTED = 'requested';
    case PASSED    = 'passed';
    case FAILED    = 'failed';

    public static function labels(): array
    {
        return [
            'requested' => __('QA check requested'),
            'passed'    => __('QA passed'),
            'failed'    => __('QA failed'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'requested' => ['tooltip' => __('QA check requested'), 'icon' => 'fal fa-vial', 'class' => 'text-amber-500', 'color' => 'amber'],
            'passed'    => ['tooltip' => __('QA passed'), 'icon' => 'fal fa-shield-check', 'class' => 'text-green-600', 'color' => 'green'],
            'failed'    => ['tooltip' => __('QA failed'), 'icon' => 'fal fa-shield', 'class' => 'text-red-500', 'color' => 'red'],
        ];
    }
}
