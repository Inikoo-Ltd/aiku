<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\Offer;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Carbon;

enum OfferFreshnessEnum: string
{
    use EnumHelperTrait;

    case RUNNING   = 'running';
    case SCHEDULED = 'scheduled';
    case RECENT    = 'recent';
    case AGEING    = 'ageing';
    case STALE     = 'stale';
    case NEVER     = 'never';

    public const int RECENT_MONTHS = 3;
    public const int AGEING_MONTHS = 6;

    public static function labels(): array
    {
        return [
            self::RUNNING->value   => __('Running'),
            self::SCHEDULED->value => __('Scheduled'),
            self::RECENT->value    => __('Recent'),
            self::AGEING->value    => __('Ageing'),
            self::STALE->value     => __('Stale'),
            self::NEVER->value     => __('Never offered'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            self::RUNNING->value   => [
                'tooltip'    => self::labels()[self::RUNNING->value],
                'class'      => 'bg-green-500 animate-pulse',
                'text_class' => 'text-green-500',
            ],
            self::SCHEDULED->value => [
                'tooltip'    => self::labels()[self::SCHEDULED->value],
                'class'      => 'bg-sky-500',
                'text_class' => 'text-sky-500',
            ],
            self::RECENT->value    => [
                'tooltip'    => self::labels()[self::RECENT->value],
                'class'      => 'bg-gray-400',
                'text_class' => 'text-gray-400',
            ],
            self::AGEING->value    => [
                'tooltip'    => self::labels()[self::AGEING->value],
                'class'      => 'bg-amber-500',
                'text_class' => 'text-amber-500',
            ],
            self::STALE->value     => [
                'tooltip'    => self::labels()[self::STALE->value],
                'class'      => 'bg-red-500',
                'text_class' => 'text-red-500',
            ],
            self::NEVER->value     => [
                'tooltip'    => self::labels()[self::NEVER->value],
                'class'      => 'bg-red-500',
                'text_class' => 'text-red-500',
            ],
        ];
    }

    public function severity(): int
    {
        return match ($this) {
            self::RUNNING   => 0,
            self::SCHEDULED => 1,
            self::RECENT    => 2,
            self::AGEING    => 3,
            self::STALE     => 4,
            self::NEVER     => 5,
        };
    }

    /**
     * @param  array{state?: string|null, start_at?: string|null, end_at?: string|null}|null  $offer
     */
    public static function fromOffer(?array $offer): self
    {
        if (!$offer) {
            return self::NEVER;
        }

        $state = OfferStateEnum::tryFrom((string) ($offer['state'] ?? ''));

        if ($state == OfferStateEnum::ACTIVE) {
            return self::RUNNING;
        }

        if ($state == OfferStateEnum::IN_PROCESS) {
            return self::SCHEDULED;
        }

        $reference = self::referenceDate($offer);

        if (!$reference) {
            return self::STALE;
        }

        $months = $reference->diffInMonths(now());

        if ($months < self::RECENT_MONTHS) {
            return self::RECENT;
        }

        if ($months < self::AGEING_MONTHS) {
            return self::AGEING;
        }

        return self::STALE;
    }

    /**
     * @param  array{state?: string|null, start_at?: string|null, end_at?: string|null}|null  $offer
     *
     * @return array{value: string, label: string, tooltip: string, class: string, text_class: string}
     */
    public static function badge(?array $offer): array
    {
        $freshness = self::fromOffer($offer);
        $reference = self::referenceDate($offer);

        $tooltip = self::labels()[$freshness->value];
        if ($reference && !in_array($freshness, [self::RUNNING, self::SCHEDULED])) {
            $tooltip .= ' · '.__('last offer :age', ['age' => $reference->diffForHumans()]);
        }

        return [
            'value'      => $freshness->value,
            'label'      => self::labels()[$freshness->value],
            'tooltip'    => $tooltip,
            'class'      => self::stateIcon()[$freshness->value]['class'],
            'text_class' => self::stateIcon()[$freshness->value]['text_class'],
        ];
    }

    /**
     * @param  array<int, array{state?: string|null, start_at?: string|null, end_at?: string|null}>  $offers
     *
     * @return array{value: string, label: string, tooltip: string, class: string, text_class: string}
     */
    public static function worstBadge(array $offers): array
    {
        $worstOffer    = null;
        $worstSeverity = -1;

        foreach ($offers as $offer) {
            $severity = self::fromOffer($offer)->severity();
            if ($severity > $worstSeverity) {
                $worstSeverity = $severity;
                $worstOffer    = $offer;
            }
        }

        return self::badge($worstOffer);
    }

    /**
     * @param  array{start_at?: string|null, end_at?: string|null}|null  $offer
     */
    private static function referenceDate(?array $offer): ?Carbon
    {
        $date = $offer['end_at'] ?? $offer['start_at'] ?? null;

        return $date ? Carbon::parse($date) : null;
    }
}
