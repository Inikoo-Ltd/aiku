<?php

/*
 * author Louis Perez
 * created on 03-12-2025-13h-08m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Web;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Website;
use App\Models\Web\Webpage;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType ;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebBlockToWebsiteAndChild extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    public function handle(WebBlockType $newWebBlock, Website $website, string $marginal): WebBlockType
    {
        $type  = $marginal === 'products' ? 'list_product' : $marginal;
        $names = WebBlockType::where('category', $type)->pluck('name')->toArray();

        $webpages = $website->webpages()
            ->where(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhereRaw("published_layout->'web_blocks' @> '[{\"name\": \"$name\"}]'");
                }
            })->chunkById(500, function ($webpages) use ($names, $newWebBlock) {
                foreach ($webpages as $webpage) {
                    $modified = $this->modifyLayout($webpage->published_layout, $names, $newWebBlock->slug);
                    if (empty($modified)) continue;

                    $webpage->updateQuietly(['published_layout' => $modified['layout']]);

                    $blockId = data_get($modified['layout'], "web_blocks.{$modified['index']}.id");
                    if ($blockId) {
                        $webpage->webBlocks()->find($blockId)?->updateQuietly(['web_block_type_id' => $newWebBlock->id]);
                    }

                    if ($live = $webpage->liveSnapshot) {
                        $liveLayout = $this->applyIndexChange($live->layout, $modified['index'], $newWebBlock->slug);
                        $live->updateQuietly(['layout' => $liveLayout]);
                    }

                    if ($unpublished = $webpage->unpublishedSnapshot) {
                        $unLayout = $this->applyIndexChange($unpublished->layout, $modified['index'], $newWebBlock->slug);
                        $unpublished->updateQuietly(['layout' => $unLayout]);
                    }
                }
            });

        return $newWebBlock;
    }


    public function modifyLayout(array $layout, array $names, string $slug): array
    {
        $index = collect($layout['web_blocks'] ?? [])->whereIn('name', $names)->keys()->first();

        if ($index === null) return [];

        data_set($layout, "web_blocks.$index.type", $slug);

        return [
            'layout' => $layout,
            'index'  => $index,
        ];
    }


    public function applyIndexChange(array $layout, int $index, string $slug): array
    {
        data_set($layout, "web_blocks.$index.type", $slug);
        return $layout;
    }
}

