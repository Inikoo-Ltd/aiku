<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Jun 2025 15:31:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\Iris\GetIrisBlockSubDepartment;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockDepartmentDescription;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockDepartment;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockFamiliesOverview;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockCollection;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSubDepartments;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockFamilies;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockFamilyDescription;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSeeAlso;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockBlog;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockRecommendationsCRB;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockRecommendationsFromMaster;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockRecommendationsProductCategoriesFromMaster;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockLuigiRecommendations;
use App\Actions\Web\WebBlock\Iris\GetWebBlockProduct;
use App\Actions\Web\WebBlock\Iris\GetWebBlockProducts;
use App\Actions\Web\WebBlock\Iris\GetIrisRelatedProductCategory;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockSubDepartmentsThree;
use App\Actions\Web\Webpage\UI\SanitiseImagesWebBlock;
use Illuminate\Support\Arr;

trait WithFillIrisWebBlocks
{
    public function fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock, bool $isIris = true)
    {
        $webBlockType = Arr::get($webBlock, 'type');

        // Old. Commented it out
        // if ($webBlockType == 'department-description-1') {
        //     $parsedWebBlocks[$key] = GetIrisWebBlockDepartment::run($webpage, $webBlock);
        if (in_array($webBlockType, ['department-description-1', 'department-description-2'])) {
            $parsedWebBlocks[$key] = GetIrisWebBlockDepartmentDescription::run($webpage, $webBlock);
        } elseif ($webBlockType == 'sub-department-description-1') {
            $parsedWebBlocks[$key] = GetIrisBlockSubDepartment::run($webpage, $webBlock);
        } elseif ($webBlockType == 'collection-description-1') {
            $parsedWebBlocks[$key] = GetIrisWebBlockCollection::run($webpage, $webBlock);
        } elseif (str_starts_with($webBlockType, 'families-') &&  str_ends_with($webBlockType, '-overview')) {
            $parsedWebBlocks[$key] = GetIrisWebBlockFamiliesOverview::run($webpage, $webBlock);
        } elseif ($webBlockType == 'sub-departments-3') {
            $parsedWebBlocks[$key] = GetIrisWebBlockSubDepartmentsThree::run($webpage, $webBlock);
        } elseif ($webBlockType !== 'sub-departments-3' && str_contains($webBlockType, 'sub-departments-')) {
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
            $parsedWebBlocks[$key] = GetIrisWebBlockRecommendationsProductCategoriesFromMaster::run($webpage, $webBlock);
        } elseif ($webBlockType == 'recommendation-from-master') {
            $parsedWebBlocks[$key] = GetIrisWebBlockRecommendationsFromMaster::run($webpage, $webBlock);
        } elseif (in_array($webBlockType, ['luigi-last-seen-1', 'luigi-item-alternatives-1'])) {
            $parsedWebBlocks[$key] = GetIrisWebBlockLuigiRecommendations::run($webpage, $webBlock);
        } elseif ($webBlockType == 'images') {
            $parsedWebBlocks[$key] = SanitiseImagesWebBlock::run($webBlock);
        } elseif ($webBlockType == 'relatedProductCategory') {
            $parsedWebBlocks[$key] = GetIrisRelatedProductCategory::run($webBlock);
        } else {
            $parsedWebBlocks[$key] = $webBlock;
        }

        return $parsedWebBlocks;
    }
}
