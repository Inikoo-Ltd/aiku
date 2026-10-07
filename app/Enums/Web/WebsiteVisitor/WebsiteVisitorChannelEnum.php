<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\WebsiteVisitor;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\EnumHelperTrait;

enum WebsiteVisitorChannelEnum: string
{
    use EnumHelperTrait;

    public const string EMAIL_TYPE    = 'email';
    public const string INTERNAL_TYPE = 'internal';

    case PAID     = 'paid';
    case ORGANIC  = 'organic';
    case EMAIL    = 'email';
    case AI       = 'ai';
    case OTHER    = 'other';
    case DIRECT   = 'direct';
    case INTERNAL = 'internal';

    public static function labels(): array
    {
        return [
            'paid'     => __('Paid ads'),
            'organic'  => __('Organic'),
            'email'    => __('Email'),
            'ai'       => __('AI'),
            'other'    => __('Other'),
            'direct'   => __('Direct'),
            'internal' => __('Internal'),
        ];
    }

    public static function fromType(?string $type): ?self
    {
        return match ($type) {
            null                => null,
            self::EMAIL_TYPE    => self::EMAIL,
            self::INTERNAL_TYPE => self::INTERNAL,
            default             => self::tryFrom(TrafficSourcesTypeEnum::tryFrom($type)?->group()['key'] ?? ''),
        };
    }

    public static function typeLabel(?string $type): ?string
    {
        return match ($type) {
            null                => null,
            self::EMAIL_TYPE    => __('Email'),
            self::INTERNAL_TYPE => __('Our own websites'),
            default             => TrafficSourcesTypeEnum::labels()[$type] ?? $type,
        };
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        $types = collect(TrafficSourcesTypeEnum::cases())
            ->filter(fn (TrafficSourcesTypeEnum $type) => $type->group()['key'] === $this->value)
            ->map(fn (TrafficSourcesTypeEnum $type) => $type->value)
            ->values()
            ->all();

        return match ($this) {
            self::EMAIL    => [...$types, self::EMAIL_TYPE],
            self::INTERNAL => [self::INTERNAL_TYPE],
            default        => $types,
        };
    }
}
