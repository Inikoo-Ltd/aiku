<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Jun 2025 15:31:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\Iris\GetIrisBlockSubDepartment;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockDepartmentDescription;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockFamiliesOverview;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockCollection;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSubDepartments;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockFamilies;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockFamilyDescription;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSeeAlso;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockBanner;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockCarousel;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSlider;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockBlog;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockRecommendationsCRB;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockRecommendationsFromMaster;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockRecommendationsProductCategoriesFromMaster;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockLuigiRecommendations;
use App\Actions\Web\WebBlock\Iris\GetWebBlockProduct;
use App\Actions\Web\WebBlock\Iris\GetWebBlockProducts;
use App\Actions\Web\WebBlock\Iris\GetIrisRelatedProductCategory;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSubDepartmentsThree;
use App\Actions\Web\WebBlock\Iris\GetIrisFaqDepartment;
use App\Actions\Web\WebBlock\Iris\GetIrisTopFamilies;
use App\Actions\Web\Webpage\UI\SanitiseImagesWebBlock;
use Illuminate\Support\Arr;

trait WithFillIrisWebBlocks
{
    public function fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock, bool $isIris = true)
    {
        $webBlockType = Arr::get($webBlock, 'type');

        if (in_array($webBlockType, ['department-description-1', 'department-description-2'])) {
            $departmentData = GetIrisWebBlockDepartmentDescription::run($webpage, $webBlock);
            if ($departmentData) {
                $parsedWebBlocks[$key] = $departmentData;
            } else {
                unset($parsedWebBlocks[$key]);
            }
        } elseif ($webBlockType == 'sub-department-description-1') {
            $parsedWebBlocks[$key] = GetIrisBlockSubDepartment::run($webpage, $webBlock);
        } elseif ($webBlockType == 'collection-description-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockCollection::run($webpage, $webBlock);
        } elseif (str_starts_with($webBlockType, 'families-') && str_ends_with($webBlockType, '-overview')) {
            $parsedWebBlocks[$key] = GetIrisWebBlockFamiliesOverview::run($webpage, $webBlock);
        } elseif ($webBlockType == 'sub-departments-3') {
            $webBlockData = GetIrisWebBlockSubDepartmentsThree::run($webpage, $webBlock);
            if ($webBlockData) {
                $parsedWebBlocks[$key] = $webBlockData;
            } else {
                unset($parsedWebBlocks[$key]);
            }
        } elseif (str_contains($webBlockType, 'sub-departments-')) {
            $parsedWebBlocks[$key] = GetIrisWebBlockSubDepartments::run($webpage, $webBlock);
        } elseif (str_contains($webBlockType, 'families-')) {
            $parsedWebBlocks[$key] = GetIrisWebBlockFamilies::run($webpage, $webBlock);
        } elseif (str_contains($webBlockType, 'products-')) {
            $parsedWebBlocks[$key] = GetWebBlockProducts::run($webpage, $webBlock);
        } elseif ($webBlockType == 'family-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockFamilyDescription::run($webpage, $webBlock);
        } elseif (in_array($webBlockType, ['family-2', 'family-2-extra-description', 'family-3', 'family-3-extra-description'])) {
            $parsedWebBlocks[$key] = GetIrisWebBlockFamilyDescription::run($webpage, $webBlock);
        } elseif (str_starts_with($webBlockType, 'product-')) {
            $parsedWebBlocks[$key] = GetWebBlockProduct::run($webpage, $webBlock, $isIris);
        } elseif ($webBlockType == 'see-also-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockSeeAlso::run($webpage, $webBlock);
        } elseif ($webBlockType == 'blog') {
            $parsedWebBlocks[$key] = GetIrisWebBlockBlog::run($webpage, $webBlock);
        } elseif ($webBlockType == 'recommendation-customer-recently-bought-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockRecommendationsCRB::run($webpage, $webBlock);
        } elseif ($webBlockType == 'recommendation-product-category-from-master') {
            $webBlockData = GetIrisWebBlockRecommendationsProductCategoriesFromMaster::run($webpage, $webBlock);
            if ($webBlockData) {
                $parsedWebBlocks[$key] = $webBlockData;
            } else {
                unset($parsedWebBlocks[$key]);
            }
        } elseif ($webBlockType == 'recommendation-from-master') {
            $parsedWebBlocks[$key] = GetIrisWebBlockRecommendationsFromMaster::run($webpage, $webBlock);
        } elseif (in_array($webBlockType, ['luigi-last-seen-1', 'luigi-item-alternatives-1'])) {
            $parsedWebBlocks[$key] = GetIrisWebBlockLuigiRecommendations::run($webpage, $webBlock);
        } elseif ($webBlockType == 'banner') {
            $parsedWebBlocks[$key] = GetIrisWebBlockBanner::run($webpage, $webBlock);
        } elseif ($webBlockType == 'carousel-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockCarousel::run($webpage, $webBlock);
        } elseif ($webBlockType == 'slider-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockSlider::run($webpage, $webBlock);
        } elseif ($webBlockType == 'images') {
            $parsedWebBlocks[$key] = SanitiseImagesWebBlock::run($webBlock);
        } elseif ($webBlockType == 'relatedProductCategory') {
            $parsedWebBlocks[$key] = GetIrisRelatedProductCategory::run($webBlock);
        } elseif ($webBlockType == 'faq-department') {
            $webBlockData = GetIrisFaqDepartment::run($webpage, $webBlock);
            if ($webBlockData) {
                $parsedWebBlocks[$key] = $webBlockData;
            } else {
                unset($parsedWebBlocks[$key]);
            }
        } elseif ($webBlockType == 'top-families') {
            $webBlockData = GetIrisTopFamilies::run($webpage, $webBlock);
            if ($webBlockData) {
                $parsedWebBlocks[$key] = $webBlockData;
            } else {
                unset($parsedWebBlocks[$key]);
            }
        } else {
            $parsedWebBlocks[$key] = $webBlock;
        }

        return $parsedWebBlocks;
    }
}
