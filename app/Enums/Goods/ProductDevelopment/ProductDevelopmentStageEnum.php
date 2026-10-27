<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-15h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

enum ProductDevelopmentStageEnum: string
{
    use EnumHelperTrait;

    case BRIEF               = 'brief';
    case PRODUCT_SPECS       = 'product_specs';
    case COMMERCIAL_TERMS    = 'commercial_terms';
    case PACK_CONTENT        = 'pack_content';
    case FORMULATION         = 'formulation';
    case CONCEPT             = 'concept';
    case ARTWORK             = 'artwork';
    case FINAL_APPROVAL      = 'final_approval';
    case ARTWORK_TO_SUPPLIER = 'artwork_to_supplier';

    public static function labels(): array
    {
        return [
            'brief'               => __('Brief'),
            'product_specs'       => __('Product specs'),
            'commercial_terms'    => __('Commercial terms'),
            'pack_content'        => __('Pack content'),
            'formulation'         => __('Formulation & samples'),
            'concept'             => __('Concept'),
            'artwork'             => __('Artwork'),
            'final_approval'      => __('Final approval'),
            'artwork_to_supplier' => __('Artwork to supplier'),
        ];
    }

    public function position(): int
    {
        return match ($this) {
            self::BRIEF => 1,
            self::PRODUCT_SPECS, self::COMMERCIAL_TERMS, self::PACK_CONTENT, self::FORMULATION => 2,
            self::CONCEPT => 3,
            self::ARTWORK => 4,
            self::FINAL_APPROVAL => 5,
            self::ARTWORK_TO_SUPPLIER => 6,
        };
    }

    public function subPosition(): ?string
    {
        return match ($this) {
            self::PRODUCT_SPECS => 'a',
            self::COMMERCIAL_TERMS => 'b',
            self::PACK_CONTENT => 'c',
            self::FORMULATION => 'd',
            default => null,
        };
    }

    public function role(): ProductDevelopmentMemberRoleEnum
    {
        return match ($this) {
            self::BRIEF, self::FINAL_APPROVAL => ProductDevelopmentMemberRoleEnum::BUYER,
            self::PRODUCT_SPECS => ProductDevelopmentMemberRoleEnum::AGENT,
            self::COMMERCIAL_TERMS, self::ARTWORK_TO_SUPPLIER => ProductDevelopmentMemberRoleEnum::PURCHASING,
            self::PACK_CONTENT => ProductDevelopmentMemberRoleEnum::COMPLIANCE,
            self::CONCEPT, self::ARTWORK => ProductDevelopmentMemberRoleEnum::DESIGNER,
            self::FORMULATION => ProductDevelopmentMemberRoleEnum::PRODUCTION,
        };
    }

    public function defaultDurationDays(): int
    {
        return (int) config('product_development.stage_duration_days.'.$this->value, 0);
    }

    /**
     * @return array<int, string>
     */
    public static function positionLabels(?ProductDevelopmentKindEnum $kind = null): array
    {
        return [
            1 => __('Brief'),
            2 => $kind === ProductDevelopmentKindEnum::RECIPE ? __('Specs and pack content') : __('Specs, terms and pack content'),
            3 => __('Concept'),
            4 => __('Artwork'),
            5 => __('Final approval'),
            6 => __('Artwork to supplier'),
            7 => __('Done'),
        ];
    }

    public static function positionLabel(int $position, ?ProductDevelopmentKindEnum $kind = null): string
    {
        return self::positionLabels($kind)[$position] ?? (string) $position;
    }

    /**
     * Who owns the stage for a development of this kind: an in-house development has no agent, so its
     * production team fills in the product specs.
     */
    public function roleFor(ProductDevelopmentKindEnum $kind): ProductDevelopmentMemberRoleEnum
    {
        return $kind === ProductDevelopmentKindEnum::RECIPE && $this === self::PRODUCT_SPECS ? ProductDevelopmentMemberRoleEnum::PRODUCTION : $this->role();
    }

    /**
     * The stages a development of this kind goes through: only in-house developments have formulation & samples.
     *
     * @return array<int, self>
     */
    public static function forKind(ProductDevelopmentKindEnum $kind): array
    {
        return array_values(array_filter(self::cases(), fn (self $stage) => $kind === ProductDevelopmentKindEnum::RECIPE
            ? $stage !== self::COMMERCIAL_TERMS
            : $stage !== self::FORMULATION));
    }

    public static function lastPosition(): int
    {
        return self::ARTWORK_TO_SUPPLIER->position();
    }
}
