<?php

namespace App\Enums\HumanResources\Concurrency;

enum LeaveConcurrencyRuleTypeEnum: string
{
    case QUOTA = 'quota';
    case DEPENDENCY = 'dependency';
    case BIDIRECTIONAL = 'bidirectional';

    public function label(): string
    {
        return match ($this) {
            self::QUOTA => __('Quota / Mutual Conflict'),
            self::DEPENDENCY => __('Dependency (One-way)'),
            self::BIDIRECTIONAL => __('Bidirectional'),
        };
    }
}
