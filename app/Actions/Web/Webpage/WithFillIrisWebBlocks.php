<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Jun 2025 15:31:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\GetBanner;
use App\Actions\Web\WebBlock\GetBlockSubDepartment;
use App\Actions\Web\WebBlock\GetWebBlockBlog;
use App\Actions\Web\WebBlock\GetWebBlockCollection;
use App\Actions\Web\WebBlock\GetWebBlockDepartment;
use App\Actions\Web\WebBlock\GetWebBlockFamilies;
use App\Actions\Web\WebBlock\GetWebBlockFamily;
use App\Actions\Web\WebBlock\GetWebBlockLuigiRecommendations;
use App\Actions\Web\WebBlock\GetWebBlockProduct;
use App\Actions\Web\WebBlock\GetWebBlockProducts;
use App\Actions\Web\WebBlock\GetWebBlockRecommendationsCRB;
use App\Actions\Web\WebBlock\GetWebBlockSeeAlso;
use App\Actions\Web\WebBlock\GetWebBlockSubDepartments;
use Illuminate\Support\Arr;

trait WithFillIrisWebBlocks
{
    public function fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock, bool $isLoggedIn, bool $isIris = true)
    {
        $webBlockType = Arr::get($webBlock, 'type');

        if ($webBlockType === 'banner') {
            $parsedWebBlocks[$key] = GetBanner::run($webBlock);
        } elseif ($webBlockType == 'department-description') {
            $parsedWebBlocks[$key] = GetWebBlockDepartment::run($webpage, $webBlock);
        } elseif ($webBlockType == 'sub-department-description') {
            $parsedWebBlocks[$key] = GetBlockSubDepartment::run($webpage, $webBlock);
        } elseif ($webBlockType == 'collection-description') {
            $parsedWebBlocks[$key] = GetWebBlockCollection::run($webpage, $webBlock);
        } elseif (str_contains($webBlockType, 'sub-departments-')) {
            $parsedWebBlocks[$key] = GetWebBlockSubDepartments::run($webpage, $webBlock);
        } elseif (str_contains($webBlockType, 'families-')) {
            $parsedWebBlocks[$key] = GetWebBlockFamilies::run($webpage, $webBlock);
        } elseif (str_contains($webBlockType, 'products-')) {
            $parsedWebBlocks[$key] = GetWebBlockProducts::run($webpage, $webBlock, $isLoggedIn);
        } elseif ($webBlockType == 'family-1') {
            $parsedWebBlocks[$key] = GetWebBlockFamily::run($webpage, $webBlock);
        } elseif (str_contains($webBlockType, 'product-')) {
            $parsedWebBlocks[$key] = GetWebBlockProduct::run($webpage, $webBlock, $isIris);
        } elseif ($webBlockType == 'see-also-1') {
            $parsedWebBlocks[$key] = GetWebBlockSeeAlso::run($webpage, $webBlock);
        } elseif ($webBlockType == 'blog') {
            $parsedWebBlocks[$key] = GetWebBlockBlog::run($webpage, $webBlock);
        } elseif ($webBlockType == 'recommendation-customer-recently-bought-1') {
            $parsedWebBlocks[$key] = GetWebBlockRecommendationsCRB::run($webpage, $webBlock);
        } elseif (in_array($webBlockType, ['luigi-last-seen-1', 'luigi-item-alternatives-1'])) {
            $parsedWebBlocks[$key] = GetWebBlockLuigiRecommendations::run($webpage, $webBlock);
        } else {
            $parsedWebBlocks[$key] = $webBlock;
        }

        return $parsedWebBlocks;
    }
}
