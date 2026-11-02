<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-10h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentDesignRound;

/**
 * The concept runs as versions: a new version can only start when the round has none yet or was sent
 * back. Packaging and label artwork live in design sections instead (ProductDevelopmentArtworks).
 */
class ProductDevelopmentDesignRounds
{
    public const string CHECK_MATCHES_BRIEF    = 'matches_brief';
    public const string CHECK_COMPLIANCE_SPACE = 'compliance_space';
    public const string CHECK_PRINT_LIMITS     = 'print_limits';

    /**
     * @return array<string, array{label: string, by: string}>
     */
    public static function checks(ProductDevelopment $productDevelopment): array
    {
        $checks = [
            self::CHECK_MATCHES_BRIEF    => ['label' => __('Matches the brief'), 'by' => 'approver'],
            self::CHECK_COMPLIANCE_SPACE => ['label' => __('Space reserved for compliance text'), 'by' => 'approver'],
            self::CHECK_PRINT_LIMITS     => ['label' => __('Within factory print limits (agent confirmed)'), 'by' => 'agent'],
        ];

        if ($productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE) {
            unset($checks[self::CHECK_PRINT_LIMITS]);
        }

        return $checks;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductDevelopmentDesignRound>
     */
    public static function scope(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round)
    {
        return $productDevelopment->designRounds()->where('round', $round->value);
    }

    public static function latest(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round): ?ProductDevelopmentDesignRound
    {
        return self::scope($productDevelopment, $round)->orderByDesc('version')->first();
    }

    public static function isApproved(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round): bool
    {
        return self::scope($productDevelopment, $round)->where('status', ProductDevelopmentDesignRoundStatusEnum::APPROVED->value)->exists();
    }

    public static function hasAnyVersion(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round): bool
    {
        return self::scope($productDevelopment, $round)->exists();
    }

    /**
     * Done for the rest of the development. The concept cannot be skipped, so this is approved.
     */
    public static function isSettled(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round): bool
    {
        return self::isApproved($productDevelopment, $round);
    }

    public static function isArtworkLocked(ProductDevelopment $productDevelopment): bool
    {
        return !empty($productDevelopment->data['artwork_locked_at'] ?? null);
    }

    public static function canStartVersion(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round): bool
    {
        if (self::isArtworkLocked($productDevelopment)) {
            return false;
        }

        $latest = self::latest($productDevelopment, $round);

        return !$latest || $latest->status === ProductDevelopmentDesignRoundStatusEnum::CHANGES_REQUESTED;
    }

    public static function allChecksTicked(ProductDevelopmentDesignRound $designRound): bool
    {
        return collect(array_keys(self::checks($designRound->productDevelopment)))->every(fn (string $key) => !empty($designRound->data['checks'][$key] ?? null));
    }
}
