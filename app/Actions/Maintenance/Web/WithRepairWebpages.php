<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait WithRepairWebpages
{
    use WithStoreWebpage;
    use WithReorderWebpages;

    protected function getWebpageBlocksByType(Webpage $webpage, string|array $type): \Illuminate\Support\Collection
    {
        return DB::table('model_has_web_blocks')
            ->select(['web_blocks.layout', 'web_blocks.id', 'web_block_types.code as type','model_has_web_blocks.id as model_has_web_blocks_id'])
            ->leftJoin('web_blocks', 'web_blocks.id', '=', 'model_has_web_blocks.web_block_id')
            ->leftJoin('web_block_types', 'web_block_types.id', '=', 'web_blocks.web_block_type_id')
            ->when(
                is_array($type),
                fn ($q) => $q->whereIn('web_block_types.code', $type),
                fn ($q) => $q->where('web_block_types.code', $type)
            )
            ->where('model_has_web_blocks.model_type', 'Webpage')
            ->where('model_has_web_blocks.model_id', $webpage->id)
            ->get();
    }

    protected function fetchUsedTemplate(Webpage $webpage, WebBlockTemplateEnum $webBlockTemplateType): string
    {
        $website = $webpage->website;
        $scope = $webBlockTemplateType->value;

        $liveWebBlockSnapshot = $website->{"live{$scope}Snapshot"};
        $unpublishedWebBlockSnapshot = $website->{"unpublished{$scope}Snapshot"};

        return data_get($liveWebBlockSnapshot?->layout, 'code', array_first($webBlockTemplateType->templateCodes())); // Get published WebBlock layout code
    }

    protected function normalizeWebBlockByType(Webpage $webpage, array $webBlockTemplateCodes, WebBlockTemplateEnum $webBlockTemplateType): void
    {
        $website = $webpage->website;

        $scope = $webBlockTemplateType->value;

        if (!in_array($scope, WebBlockTemplateEnum::values()) || !$website) {
            return;
        }

        $usedWebBlockTemplateCodes = $this->fetchUsedTemplateCodes($webpage, $webBlockTemplateType);

        if ($usedWebBlockTemplateCodes) {
            $unusedWebBlockTemplateCodes = array_diff($webBlockTemplateCodes, $usedWebBlockTemplateCodes);

            // Remove multiple WebBlock if it exists (besides the used one)
            foreach ($unusedWebBlockTemplateCodes as $unusedWebBlockCode) {
                $unusedWebBlock = $this->getWebpageBlocksByType($webpage, $unusedWebBlockCode);
                if (count($unusedWebBlock) > 0) {
                    $webpage
                        ->modelHasWebBlocks()
                        ->whereIn('id', $unusedWebBlock->pluck('model_has_web_blocks_id'))
                        ->delete();

                    $webpage
                        ->webBlocks()
                        ->whereIn('web_blocks.id', $unusedWebBlock->pluck('id'))
                        ->delete();
                }
            }

            foreach ($usedWebBlockTemplateCodes as $usedWebBlockTemplateCode) {
                $usedWebBlocks = $this->getWebpageBlocksByType($webpage, $usedWebBlockTemplateCode);
                if (count($usedWebBlocks) == 0) {
                    $this->createWebBlockFromSavedTemplate($webpage, $webBlockTemplateType, $usedWebBlockTemplateCode);
                } elseif (count($usedWebBlocks) > 1) {
                    $usedWebBlocks->pop();

                    foreach ($usedWebBlocks as $webBlock) {
                        $webpage
                            ->modelHasWebBlocks()
                            ->where('id', data_get($webBlock, 'model_has_web_blocks_id'))
                            ->delete();

                        $webpage
                            ->webBlocks()
                            ->where('web_blocks.id', data_get($webBlock, 'id'))
                            ->delete();
                    }
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function fetchUsedTemplateCodes(Webpage $webpage, WebBlockTemplateEnum $webBlockTemplateType): array
    {
        $liveWebBlockSnapshot = $webpage->website->{"live{$webBlockTemplateType->value}Snapshot"};
        $defaultTemplateCode  = array_first($webBlockTemplateType->templateCodes());

        if (in_array($webBlockTemplateType, [WebBlockTemplateEnum::DEPARTMENT_DESCRIPTION, WebBlockTemplateEnum::FAMILY_DESCRIPTION])) {
            $usedTemplateCodes = array_values(array_intersect(array_keys($liveWebBlockSnapshot?->layout ?? []), $webBlockTemplateType->templateCodes()));

            return $usedTemplateCodes ?: [$defaultTemplateCode];
        }

        return [data_get($liveWebBlockSnapshot?->layout, 'code', $defaultTemplateCode)];
    }

    protected function deleteWebBlocksByType(Webpage $webpage, WebBlockTemplateEnum $scope)
    {
        foreach ($scope->templateCodes() as $unusedWebBlockCode) {
            $unusedWebBlock = $this->getWebpageBlocksByType($webpage, $unusedWebBlockCode);
            if (count($unusedWebBlock) > 0) {
                $webpage
                    ->modelHasWebBlocks()
                    ->whereIn('id', $unusedWebBlock->pluck('model_has_web_blocks_id'))
                    ->delete();

                $webpage
                    ->webBlocks()
                    ->whereIn('web_blocks.id', $unusedWebBlock->pluck('id'))
                    ->delete();
            }
        }
    }

    protected function deleteWebBlocksByCode(Webpage $webpage, string $scope)
    {
        $unusedWebBlock = $this->getWebpageBlocksByType($webpage, $scope);
        if (count($unusedWebBlock) > 0) {
            $webpage
                ->modelHasWebBlocks()
                ->whereIn('id', $unusedWebBlock->pluck('model_has_web_blocks_id'))
                ->delete();

            $webpage
                ->webBlocks()
                ->whereIn('web_blocks.id', $unusedWebBlock->pluck('id'))
                ->delete();
        }
    }

    protected function deleteWebBlocksByModelHasID(Webpage $webpage, array $modelHasWebBlocksIDs)
    {
        $webBlocks = DB::table('model_has_web_blocks')
            ->select(['web_blocks.id'])
            ->leftJoin('web_blocks', 'web_blocks.id', '=', 'model_has_web_blocks.web_block_id')
            ->whereIn('model_has_web_blocks.id', $modelHasWebBlocksIDs)
            ->get();

        if (count($webBlocks) > 0) {
            $webpage
                ->modelHasWebBlocks()
                ->whereIn('id', $modelHasWebBlocksIDs)
                ->delete();

            $webpage
                ->webBlocks()
                ->whereIn('web_blocks.id', $webBlocks->pluck('id'))
                ->delete();
        }
    }

}
