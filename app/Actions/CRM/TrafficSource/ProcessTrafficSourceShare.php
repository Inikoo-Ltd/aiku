<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessTrafficSourceShare
{
    use AsAction;

    public const ATTRIBUTION_FIRST_TOUCH      = 'first_touch';
    public const ATTRIBUTION_LAST_TOUCH       = 'last_touch';
    public const ATTRIBUTION_LAST_NON_DIRECT  = 'last_non_direct_touch';
    public const ATTRIBUTION_LAST_PAID_TOUCH  = 'last_paid_touch';
    public const ATTRIBUTION_LINEAR           = 'linear';

    /**
     * Calculates the attribution credit share for a list of chronologically ordered marketing touches.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{type: TrafficSourcesTypeEnum, campaign_ref: string|null, share: float, is_first_touch: bool}>
     */
    public function handle(array $touches, string $attributionModel = self::ATTRIBUTION_LINEAR): array
    {
        if (empty($touches)) {
            return [];
        }

        return match ($attributionModel) {
            self::ATTRIBUTION_FIRST_TOUCH     => $this->firstTouch($touches),
            self::ATTRIBUTION_LAST_TOUCH,
            self::ATTRIBUTION_LAST_NON_DIRECT => $this->lastTouch($touches),
            self::ATTRIBUTION_LAST_PAID_TOUCH => $this->lastPaidTouch($touches),
            default                            => $this->linear($touches),
        };
    }

    /**
     * Gives 100% credit to the latest touch coming from a paid (`-ads`) traffic source. Returns an
     * empty result when the journey has no paid touch at all, since crediting an unpaid source would
     * misrepresent paid-channel reporting.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{type: TrafficSourcesTypeEnum, campaign_ref: string|null, share: float, is_first_touch: bool}>
     */
    private function lastPaidTouch(array $touches): array
    {
        $paidTouches = array_values(array_filter($touches, fn (array $touch) => $touch['type']->isPaid()));

        if (empty($paidTouches)) {
            return [];
        }

        $lastPaidTouch = $paidTouches[count($paidTouches) - 1];
        $firstKey      = $this->touchKey($touches[0]);

        return [$this->buildResult($lastPaidTouch, 1.0, $this->touchKey($lastPaidTouch) === $firstKey)];
    }

    /**
     * Identifies which unique touches in a journey assisted the conversion without receiving the
     * final credit under the given attribution model (i.e. every eligible touch except the one(s)
     * that ended up with credit). Used to report assisted vs primary conversions/revenue.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{type: TrafficSourcesTypeEnum, campaign_ref: string|null}>
     */
    public function assistingTouches(array $touches, string $attributionModel = self::ATTRIBUTION_LINEAR): array
    {
        $credited = collect($this->handle($touches, $attributionModel))
            ->map(fn (array $share) => $this->touchKey(['type' => $share['type'], 'campaign_ref' => $share['campaign_ref']]))
            ->all();

        $assisting = [];

        foreach ($this->uniqueTouches($touches) as $key => $touch) {
            if (!in_array($key, $credited, true)) {
                $assisting[] = [
                    'type'         => $touch['type'],
                    'campaign_ref' => $touch['campaign_ref'],
                ];
            }
        }

        return $assisting;
    }

    /**
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{type: TrafficSourcesTypeEnum, campaign_ref: string|null, share: float, is_first_touch: bool}>
     */
    private function firstTouch(array $touches): array
    {
        return [$this->buildResult($touches[0], 1.0, true)];
    }

    /**
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{type: TrafficSourcesTypeEnum, campaign_ref: string|null, share: float, is_first_touch: bool}>
     */
    private function lastTouch(array $touches): array
    {
        $lastTouch  = $touches[count($touches) - 1];
        $firstKey   = $this->touchKey($touches[0]);

        return [$this->buildResult($lastTouch, 1.0, $this->touchKey($lastTouch) === $firstKey)];
    }

    /**
     * Splits credit evenly across every unique traffic source and campaign combination present in the touches.
     * The last unique touch absorbs any rounding remainder so shares always total 1.0.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{type: TrafficSourcesTypeEnum, campaign_ref: string|null, share: float, is_first_touch: bool}>
     */
    private function linear(array $touches): array
    {
        $uniqueTouches = $this->uniqueTouches($touches);
        $count         = count($uniqueTouches);

        if ($count === 0) {
            return [];
        }

        $firstKey  = $this->touchKey($touches[0]);
        $share     = round(1 / $count, 2);
        $allocated = 0.0;
        $results   = [];

        foreach (array_values($uniqueTouches) as $index => $touch) {
            $isLast        = $index === $count - 1;
            $currentShare  = $isLast ? round(1 - $allocated, 2) : $share;
            $allocated    += $currentShare;

            $results[] = $this->buildResult($touch, $currentShare, $this->touchKey($touch) === $firstKey);
        }

        return $results;
    }

    /**
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<string, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}>
     */
    private function uniqueTouches(array $touches): array
    {
        $unique = [];

        foreach ($touches as $touch) {
            $key = $this->touchKey($touch);

            if (!array_key_exists($key, $unique)) {
                $unique[$key] = $touch;
            }
        }

        return $unique;
    }

    /**
     * @param array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null} $touch
     */
    private function touchKey(array $touch): string
    {
        return $touch['type']->value.'|'.($touch['campaign_ref'] ?? '');
    }

    /**
     * @param array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null} $touch
     *
     * @return array{type: TrafficSourcesTypeEnum, campaign_ref: string|null, share: float, is_first_touch: bool}
     */
    private function buildResult(array $touch, float $share, bool $isFirstTouch): array
    {
        return [
            'type'           => $touch['type'],
            'campaign_ref'   => $touch['campaign_ref'],
            'share'          => $share,
            'is_first_touch' => $isFirstTouch,
        ];
    }
}
