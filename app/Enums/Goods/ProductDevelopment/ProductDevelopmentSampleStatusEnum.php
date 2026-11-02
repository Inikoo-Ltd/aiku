<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

enum ProductDevelopmentSampleStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROGRESS      = 'in_progress';
    case READY_FOR_REVIEW = 'ready_for_review';
    case CHANGES_REQUIRED = 'changes_required';
    case APPROVED         = 'approved';

    public static function labels(): array
    {
        return [
            'in_progress'      => __('In progress'),
            'ready_for_review' => __('Ready for review'),
            'changes_required' => __('Changes required'),
            'approved'         => __('Approved'),
        ];
    }

    public function icon(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'fal fa-flask',
            self::READY_FOR_REVIEW => 'fal fa-clock',
            self::CHANGES_REQUIRED => 'fal fa-exclamation-circle',
            self::APPROVED => 'fal fa-check-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'gray',
            self::READY_FOR_REVIEW => 'blue',
            self::CHANGES_REQUIRED => 'orange',
            self::APPROVED => 'green',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::IN_PROGRESS, self::READY_FOR_REVIEW], true);
    }
}
