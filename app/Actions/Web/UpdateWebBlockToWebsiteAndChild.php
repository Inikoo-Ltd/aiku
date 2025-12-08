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
use App\Models\Web\WebBlockType ;

class UpdateWebBlockToWebsiteAndChild extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    public function handle(WebBlockType $newWebBlock, Website $website, string $marginal, array $fieldValue): WebBlockType
    {
        $type  = $marginal === 'products' ? 'list_products' : $marginal;
        $names = WebBlockType::where('category', $type)->pluck('name')->toArray();

        $webpages = $website->webpages()
            ->where(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhereRaw("published_layout->'web_blocks' @> '[{\"name\": \"$name\"}]'");
                }
            })->chunkById(500, function ($webpages) use ($names, $newWebBlock, $fieldValue) {
                foreach ($webpages as $webpage) {
                    $modified = $this->modifyLayout($webpage->published_layout, $names, $newWebBlock->slug, $fieldValue);
                    if (empty($modified)) {
                        continue;
                    }

                    $webpage->updateQuietly(['published_layout' => $modified['layout']]);

                    $blockId = data_get($modified['layout'], "web_blocks.{$modified['index']}.web_block.id");

                    $targetWebBlock = $webpage->webBlocks()->find($blockId);
                    if ($targetWebBlock) {
                        $layout = $targetWebBlock->layout;
                        data_set($layout, 'data.fieldValue', $fieldValue);
                        $targetWebBlock->updateQuietly([
                            'web_block_type_id' => $newWebBlock->id,
                            'layout'            => $layout
                        ]);
                    }

                    if (($live = $webpage->liveSnapshot) && $targetWebBlock?->layout) {
                        $liveLayout = $this->applyIndexChange($live->layout, $modified['index'], $newWebBlock->slug, $targetWebBlock->layout);
                        $live->updateQuietly(['layout' => $liveLayout]);
                    }

                    if (($unpublished = $webpage->unpublishedSnapshot) && $targetWebBlock?->layout) {
                        $unLayout = $this->applyIndexChange($unpublished->layout, $modified['index'], $newWebBlock->slug, $targetWebBlock->layout);
                        $unpublished->updateQuietly(['layout' => $unLayout]);
                    }
                }
            });

        return $newWebBlock;
    }


    public function modifyLayout(array $layout, array $names, string $slug, array $fieldValue): array
    {
        $index = collect($layout['web_blocks'] ?? [])->whereIn('name', $names)->keys()->first();

        if ($index === null) {
            return [];
        }

        data_set($layout, "web_blocks.$index.type", $slug);
        data_set($layout, "web_blocks.$index.web_block.layout.data.fieldValue", $fieldValue);

        return [
            'layout' => $layout,
            'index'  => $index,
        ];
    }


    public function applyIndexChange(array $layout, int $index, string $slug, object $webBlockLayout): array
    {
        data_set($layout, "web_blocks.$index.type", $slug);
        data_set($layout, "web_blocks.$index.web_block.layout", (array) $webBlockLayout);

        return $layout;
    }
}
