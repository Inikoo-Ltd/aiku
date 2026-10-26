<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-15h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;

enum ProductDevelopmentFileScopeEnum: string
{
    use EnumHelperTrait;

    case SDS           = 'sds';
    case IFRA          = 'ifra';
    case CPSR          = 'cpsr';
    case TEST_REPORTS  = 'test_reports';
    case DIELINE       = 'dieline';
    case FACTORY_PHOTO = 'factory_photo';
    case ARTWORK_DRAFT = 'artwork_draft';
    case ARTWORK_FINAL = 'artwork_final';
    case OTHER         = 'other';
    case DESIGN_IMAGE  = 'design_image';
    case BRIEF_REFERENCE = 'brief_reference';
    case BRIEF_IMAGE   = 'brief_image';
    case SAMPLE_PHOTO  = 'sample_photo';
    case PACKAGING_SAMPLE_PHOTO = 'packaging_sample_photo';

    public static function labels(): array
    {
        return [
            'sds'           => 'SDS',
            'ifra'          => 'IFRA',
            'cpsr'          => 'CPSR',
            'test_reports'  => __('Test reports'),
            'dieline'       => __('Dieline'),
            'factory_photo' => __('Factory photo'),
            'artwork_draft' => __('Artwork draft'),
            'artwork_final' => __('Artwork final'),
            'other'         => __('Other'),
            'design_image'  => __('Design image'),
            'brief_reference' => __('Brief reference'),
            'brief_image'   => __('Product reference image'),
            'sample_photo'  => __('Sample photo'),
            'packaging_sample_photo' => __('Packaging sample photo'),
        ];
    }

    public function tradeUnitAttachmentScope(): ?TradeAttachmentScopeEnum
    {
        return match ($this) {
            self::SDS => TradeAttachmentScopeEnum::SDS,
            self::IFRA => TradeAttachmentScopeEnum::IFRA,
            self::CPSR => TradeAttachmentScopeEnum::CPSR,
            self::TEST_REPORTS => TradeAttachmentScopeEnum::TEST_REPORTS,
            default => null,
        };
    }

    /**
     * Files that belong to the brief: shown on the Brief tab, and removable there.
     */
    public function isBrief(): bool
    {
        return $this === self::BRIEF_REFERENCE || $this === self::BRIEF_IMAGE;
    }

    /**
     * Photos attached to a sample or an artwork: uploaded and removed there, not on the Files tab.
     */
    public function isModelPhoto(): bool
    {
        return $this === self::SAMPLE_PHOTO || $this === self::PACKAGING_SAMPLE_PHOTO;
    }

    public function isVersioned(): bool
    {
        return in_array($this, [self::SDS, self::IFRA, self::CPSR, self::DIELINE, self::ARTWORK_FINAL], true);
    }

    public function isPerSku(): bool
    {
        return $this->tradeUnitAttachmentScope() !== null || in_array($this, [self::FACTORY_PHOTO, self::DIELINE, self::BRIEF_IMAGE], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::SDS, self::IFRA, self::CPSR => 'fal fa-file-certificate',
            self::TEST_REPORTS => 'fal fa-vial',
            self::DIELINE => 'fal fa-draw-square',
            self::FACTORY_PHOTO => 'fal fa-camera',
            self::ARTWORK_DRAFT, self::ARTWORK_FINAL => 'fal fa-palette',
            self::OTHER => 'fal fa-paperclip',
            self::DESIGN_IMAGE => 'fal fa-image',
            self::BRIEF_REFERENCE => 'fal fa-file-alt',
            self::BRIEF_IMAGE => 'fal fa-images',
            self::SAMPLE_PHOTO => 'fal fa-flask',
            self::PACKAGING_SAMPLE_PHOTO => 'fal fa-box-open',
        };
    }

    /**
     * @return array<int, ProductDevelopmentMemberRoleEnum>
     */
    public function neededBy(): array
    {
        return match ($this) {
            self::SDS, self::IFRA, self::CPSR, self::TEST_REPORTS => [ProductDevelopmentMemberRoleEnum::COMPLIANCE],
            self::FACTORY_PHOTO, self::DIELINE => [ProductDevelopmentMemberRoleEnum::DESIGNER],
            self::ARTWORK_DRAFT, self::ARTWORK_FINAL => [ProductDevelopmentMemberRoleEnum::BUYER, ProductDevelopmentMemberRoleEnum::APPROVER],
            self::BRIEF_REFERENCE, self::BRIEF_IMAGE => [ProductDevelopmentMemberRoleEnum::DESIGNER],
            self::OTHER, self::DESIGN_IMAGE, self::SAMPLE_PHOTO, self::PACKAGING_SAMPLE_PHOTO => [],
        };
    }
}
