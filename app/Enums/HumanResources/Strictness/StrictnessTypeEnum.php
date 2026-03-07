<?php

namespace App\Enums\HumanResources\Strictness;

enum StrictnessTypeEnum: string
{
    case BLOCK            = 'block';
    case REQUIRE_APPROVAL = 'require_approval';

    public function label(): string
    {
        return match ($this) {
            self::BLOCK            => __('Block'),
            self::REQUIRE_APPROVAL => __('Require Approval'),
        };
    }
}
