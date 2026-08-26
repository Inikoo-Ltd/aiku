<?php

/*
 * author Louis Perez
 * created on 14-04-2026-13h-51m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web;

use App\Actions\Maintenance\Web\WithRepairWebpages;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Events\BroadcastUpdateWeblocks;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateDescriptionBlockToWebsiteAndChild
{
    use AsAction;
    use WithWebEditAuthorisation;
    use WithRepairWebpages;

    public function handle(Website $website, array $layouts, string $marginal): void
    {
        $marginalData = match($marginal) {
            'family_description'    => [
                'model_type'    => class_basename(ProductCategory::class),
                'subType'       => ProductCategoryTypeEnum::FAMILY->value,
                'codes'         => WebBlockTemplateEnum::FAMILY_DESCRIPTION->templateCodes()
            ],
            'department_description'    => [
                'model_type'    => class_basename(ProductCategory::class),
                'subType'       => ProductCategoryTypeEnum::DEPARTMENT->value,
                'codes'         => WebBlockTemplateEnum::DEPARTMENT_DESCRIPTION->templateCodes()
            ],
            default                 => null
        };

        if (!$marginalData) {
            return;
        }

        $shop = $website->shop;
        $masterShop = $shop->masterShop;

        $webpages = $website->webpages()
            ->where('model_type', data_get($marginalData, 'model_type'))
            ->where('sub_type', data_get($marginalData, 'subType'))
            ->orderBy('id');

        $progress = 0;
        $total = $webpages->clone()->count();
        $lastPercent = 0;

        foreach ($webpages->get() as $webpage) {
            Log::info("Web Slug: {$webpage->slug}");
            Log::info("Deleted WebBlockCode:", data_get($marginalData, 'codes'));
            $progress++;
            foreach (data_get($marginalData, 'codes') as $code) {
                $this->deleteWebBlocksByCode($webpage, $code);
            }

            foreach ($layouts as $code => $layout) {
                Log::info("Code: [$code]", $layout);
                $this->createWebBlock($webpage, $code, $layout);
            }

            $webpage->refresh();
            if ($webpage->sub_type === WebpageSubTypeEnum::FAMILY) {
                $this->ensureFamilyPageHasRequiredBlocks($webpage, collect(array_keys($layouts))->first(fn ($key) => !str_ends_with($key, '-extra-description')), $masterShop?->slug == 'aroma');
                $webpage->refresh();
                $this->reorderFamilyPageBlocks($webpage, collect(array_keys($layouts))->first(fn ($key) => !str_ends_with($key, '-extra-description')));
            }

            if ($webpage->sub_type === WebpageSubTypeEnum::DEPARTMENT) {
                $this->ensureDepartmentPageHasRequiredBlocks($webpage, collect(array_keys($layouts))->first(fn ($key) => !str_ends_with($key, '-extra-description')));
                $webpage->refresh();
                $this->reorderDepartmentPageBlocks($webpage, collect(array_keys($layouts))->first(fn ($key) => !str_ends_with($key, '-extra-description')));
            }

            $webpage->refresh();
            UpdateWebpageContent::run($webpage);
            $webpage->liveSnapshot?->updateQuietly(
                [
                    'layout'    => $webpage->unpublishedSnapshot->layout
                ]
            );
            if ($webpage->liveSnapshot) {
                $webpage->updateQuietly(
                    [
                        'published_layout'                => $webpage->liveSnapshot->layout,
                        'published_checksum'    => $webpage->liveSnapshot->published_checksum,
                        'is_dirty'           => false,
                    ]
                );
            }

            $percent = intval(($progress / $total) * 100);
            if ($percent >= $lastPercent + 10) {
                $lastPercent = $percent;
                BroadcastUpdateWeblocks::dispatch($percent, $website);
            }
        }

        BroadcastUpdateWeblocks::dispatch(100, $website);
    }
}
