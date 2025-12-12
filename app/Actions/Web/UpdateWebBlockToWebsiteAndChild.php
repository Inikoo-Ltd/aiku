<?php

/*
 * author Louis Perez
 * created on 03-12-2025-13h-08m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Web;

use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Events\BroadcastUpdateWeblocks;
use App\Models\Web\Website;
use App\Models\Web\WebBlockType;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWebBlockToWebsiteAndChild implements ShouldBeUnique
{
    use AsAction;
    use WithWebEditAuthorisation;

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    public function handle(Website $website, WebBlockType $newWebBlock, string $marginal, array $fieldValue): WebBlockType
    {
        $progress = 0;
        $type  = $marginal === 'products' ? 'list_products' : $marginal;
        $names = WebBlockType::where('category', $type)->pluck('name')->toArray();
        $webpages = $website->webpages()
            ->where(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhereRaw("published_layout->'web_blocks' @> '[{\"name\": \"$name\"}]'");
                }
            })->orderBy('id');

        $lastPercent = 0;
        $total = (clone $webpages)->count();

        $webpages->chunk(500, function ($webpages) use ($names, $newWebBlock, $fieldValue, $website, &$progress, &$total, &$lastPercent) {
            foreach ($webpages as $webpage) {
                $modified = $this->modifyLayout($webpage->published_layout, $names, $newWebBlock->slug, $fieldValue);
                $progress++;
                if (empty($modified)) {
                    continue;
                }

                $webpage->updateQuietly(['published_layout' => $modified['layout']]);

                $blockId = data_get($modified['layout'], "web_blocks.{$modified['index']}.web_block.id");

                $targetWebBlock = $webpage->webBlocks()->find($blockId);
                if ($layout = $targetWebBlock?->layout) {
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

                $percent = intval(($progress / $total) * 100);
                if ($percent >= $lastPercent + 10) {
                    $lastPercent = $percent;
                    BroadcastUpdateWeblocks::dispatch($percent, $website);
                }
            }
        });

        BroadcastUpdateWeblocks::dispatch(100, $website);
        
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
