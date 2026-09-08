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
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateDescriptionBlockToWebsiteAndChild
{
    use AsAction;
    use WithWebEditAuthorisation;
    use WithRepairWebpages;

    private const SEE_ALSO_DEPARTMENT_TITLE = '<strong>Trending Now</strong> - Turn Proven Products into Your Own Range';

    private const SEE_ALSO_REPLACEABLE_TITLES = ['', 'See Also'];

    private const SEE_ALSO_DEPARTMENT_PER_ROW = [
        'desktop' => 5,
        'tablet'  => 4,
        'mobile'  => 2,
    ];

    private const SEE_ALSO_DEPARTMENT_PADDING = [
        'unit'   => '%',
        'top'    => ['value' => 1],
        'bottom' => ['value' => 1],
        'left'   => ['value' => 8],
        'right'  => ['value' => 8],
    ];

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
                $this->setDepartmentSeeAlsoDefaults($webpage);
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

    /**
     * Sets the editable defaults of the "See Also" block on a department page, the values
     * stay editable in the workshop so only the ones never set are filled in.
     */
    private function setDepartmentSeeAlsoDefaults(Webpage $webpage): void
    {
        $seeAlsoBlock = $this->getWebpageBlocksByType($webpage, 'see-also-1')->first();

        if (!$seeAlsoBlock) {
            return;
        }

        $webBlock = WebBlock::find($seeAlsoBlock->id);

        if (!$webBlock) {
            return;
        }

        $layout = json_decode(json_encode($webBlock->layout), true);

        $currentTitle = trim(strip_tags((string) data_get($layout, 'data.fieldValue.title')));

        if (in_array($currentTitle, self::SEE_ALSO_REPLACEABLE_TITLES)) {
            data_set($layout, 'data.fieldValue.title', self::SEE_ALSO_DEPARTMENT_TITLE);
        }

        if (!data_get($layout, 'data.fieldValue.settings.per_row')) {
            data_set($layout, 'data.fieldValue.settings.per_row', self::SEE_ALSO_DEPARTMENT_PER_ROW);
        }

        $currentPadding = data_get($layout, 'data.fieldValue.container.properties.padding') ?? [];

        if (!array_filter(Arr::flatten($currentPadding), fn ($value) => is_numeric($value) && $value > 0)) {
            data_set($layout, 'data.fieldValue.container.properties.padding', self::SEE_ALSO_DEPARTMENT_PADDING);
        }

        $webBlock->update(['layout' => $layout]);
    }
}
