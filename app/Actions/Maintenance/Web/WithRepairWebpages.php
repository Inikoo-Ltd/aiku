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

    protected function getWebpageBlocksByType(Webpage $webpage, string $type): \Illuminate\Support\Collection
    {
        return DB::table('model_has_web_blocks')
            ->select(['web_blocks.layout', 'web_blocks.id', 'web_block_types.code as type','model_has_web_blocks.id as model_has_web_blocks_id'])
            ->leftJoin('web_blocks', 'web_blocks.id', '=', 'model_has_web_blocks.web_block_id')
            ->leftJoin('web_block_types', 'web_block_types.id', '=', 'web_blocks.web_block_type_id')
            ->where('web_block_types.code', $type)
            ->where('model_has_web_blocks.model_type', 'Webpage')
            ->where('model_has_web_blocks.model_id', $webpage->id)->get();
    }

    // 
    protected function normalizeWebBlockByType(Webpage $webpage, array $webBlockTemplateCodes, string $scope): void
    {
        $website = $webpage->website;
     
        if(!in_array($scope, WebBlockTemplateEnum::values()) || !$website) return;
        
        $liveWebBlockSnapshot = $website->{"live{$scope}Snapshot"};
        $unpublishedWebBlockSnapshot = $website->{"unpublished{$scope}Snapshot"};

        $usedWebBlockTemplateCodes = data_get($liveWebBlockSnapshot?->layout, 'code', data_get($unpublishedWebBlockSnapshot?->layout, 'code', null)); // Get published WebBlock layout code
        
        if($usedWebBlockTemplateCodes){
            $unusedWebBlockTemplateCodes = array_filter(
                $webBlockTemplateCodes,
                fn ($webBlockTemplateCode) => $webBlockTemplateCode != $usedWebBlockTemplateCodes
            );

            $countWebBlockWebBlock = $this->getWebpageBlocksByType($webpage, $usedWebBlockTemplateCodes);
            if (count($countWebBlockWebBlock) == 0) {
                $this->createWebBlock($webpage, $usedWebBlockTemplateCodes);
            }

            // Remove multiple WebBlock if it exists (besides  the used one)
            foreach($unusedWebBlockTemplateCodes as $unusedWebBlockCode) {
                $unusedWebBlock = $this->getWebpageBlocksByType($webpage, $unusedWebBlockCode); 
                if(count($unusedWebBlock) > 0) {
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
    }

}
