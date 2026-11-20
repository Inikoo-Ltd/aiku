<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 20 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Helpers;

use App\Enums\Helpers\Period\ComparePeriodEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The period a dashboard compares its figures with, as picked in the dashboard settings: a mode and,
 * for the custom mode, its dates. Read from the `compare`, `compare_from` and `compare_to` query
 * parameters; `compare=none` turns the comparison off on a dashboard that compares by default.
 */
final readonly class ComparePeriod
{
    public const string OFF = 'none';

    public function __construct(
        public ?ComparePeriodEnum $mode,
        public ?string $customFrom = null,
        public ?string $customTo = null,
    ) {
    }

    public static function fromRequest(Request $request, ?ComparePeriodEnum $default = null): self
    {
        $value = $request->query('compare');
        $value = is_string($value) ? $value : '';

        return new self(
            $value === self::OFF ? null : (ComparePeriodEnum::tryFrom($value) ?? $default),
            self::queryString($request, 'compare_from'),
            self::queryString($request, 'compare_to'),
        );
    }

    public static function previousPeriod(): self
    {
        return new self(ComparePeriodEnum::PREVIOUS_PERIOD);
    }

    /**
     * The dates to compare a period with, or null when comparing is off, the period has no start
     * (the All interval) or the custom dates are missing.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function range(?Carbon $from, ?Carbon $to): ?array
    {
        if (!$this->mode || !$from) {
            return null;
        }

        return $this->mode->range($from, $to ?? now(), $this->customFrom, $this->customTo);
    }

    public function label(): ?string
    {
        return $this->mode?->label();
    }

    /**
     * What the compare button of the dashboard settings needs.
     *
     * @return array{available: bool, mode: string|null, default_mode: string|null, options: array<int, array{value: string, label: string}>, from: string|null, to: string|null, custom_from: string|null, custom_to: string|null}
     */
    public function prop(?Carbon $from, ?Carbon $to, ?ComparePeriodEnum $default = null): array
    {
        $range = $this->range($from, $to);

        return [
            'available'    => $from !== null,
            'mode'         => $range ? $this->mode?->value : null,
            'default_mode' => $default?->value,
            'options'      => collect(ComparePeriodEnum::cases())->map(fn (ComparePeriodEnum $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])->all(),
            'from'         => $range ? $range[0]->toDateString() : null,
            'to'           => $range ? $range[1]->toDateString() : null,
            'custom_from'  => $this->customFrom,
            'custom_to'    => $this->customTo,
        ];
    }

    private static function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
