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
    case CHECKING  = 'checking';
    case PASSED    = 'passed';
    case FAILED    = 'failed';
    case SKIPPED   = 'skipped';

    public static function labels(): array
    {
        return [
            'requested' => __('QA check requested'),
            'checking'  => __('QA checking'),
            'passed'    => __('QA passed'),
            'failed'    => __('QA failed'),
            'skipped'   => __('QA skipped'),
        ];
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::REQUESTED => __('Requested'),
            self::CHECKING  => __('Checking'),
            self::PASSED    => __('Passed'),
            self::FAILED    => __('Failed'),
            self::SKIPPED   => __('Skipped'),
        };
    }

    public function isVerdict(): bool
    {
        return in_array($this, [self::PASSED, self::FAILED, self::SKIPPED], true);
    }

    public static function stateIcon(): array
    {
        return [
            'requested' => ['tooltip' => __('QA check requested'), 'icon' => 'fal fa-vial', 'class' => 'text-amber-500', 'color' => 'amber'],
            'checking'  => ['tooltip' => __('QA checking'), 'icon' => 'fal fa-search', 'class' => 'text-gray-500', 'color' => 'gray'],
            'passed'    => ['tooltip' => __('QA passed'), 'icon' => 'fal fa-shield-check', 'class' => 'text-green-600', 'color' => 'green'],
            'failed'    => ['tooltip' => __('QA failed'), 'icon' => 'fal fa-shield', 'class' => 'text-red-500', 'color' => 'red'],
            'skipped'   => ['tooltip' => __('QA skipped'), 'icon' => 'fal fa-forward', 'class' => 'text-gray-500', 'color' => 'gray'],
        ];
    }
}
