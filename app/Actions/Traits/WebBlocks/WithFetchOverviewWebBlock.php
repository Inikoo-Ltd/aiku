<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
 */

namespace App\Actions\Traits\WebBlocks;

use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithFetchOverviewWebBlock
{
    use AsAction;

    public function processOverviewData(WebBlockType $webBlockType, Webpage $webpage, $auroraBlock): array
    {
        data_set($layout, 'data.fieldValue', Arr::get($webBlockType, 'data.fieldValue'));
        $textsArray = [];
        foreach ($auroraBlock['texts'] as $text) {
            if (! isset($text['text'])) {
                continue;
            }
            $layout = $this->replaceAnchor($webpage, $text['text'], $layout); // should use WithFetchText
            $text = Arr::get($layout, 'text') ?? $text['text'];
            $this->setProperties($property, $text);
            $textsArray[] = [
                'properties' => $property,
                'text' => $text,
            ];
            data_forget($layout, 'text');
        }

        data_set($layout, 'data.fieldValue.texts.values', $textsArray);

        $imagesArray = [];
        foreach ($auroraBlock['images'] as $image) {
            if (! isset($image['src'])) {
                continue;
            }
            $this->setProperties($property, $image);
            $imagesArray[] = [
                'properties' => $property,
                'aurora_source' => $image['src'],
            ];
        }

        data_set($layout, 'data.fieldValue.images', $imagesArray);

        return $layout;
    }

    private function setProperties(&$properties, $propertiesAurora)
    {
        $top = Arr::get($propertiesAurora, 'top');
        $left = Arr::get($propertiesAurora, 'left');
        $bottom = Arr::get($propertiesAurora, 'bottom');
        $right = Arr::get($propertiesAurora, 'right');
        $width = Arr::get($propertiesAurora, 'width');
        $height = Arr::get($propertiesAurora, 'height');
        data_set($properties, 'position.top', $top ? $top.'px' : null);
        data_set($properties, 'position.left', $left ? $left.'px' : null);
        data_set($properties, 'position.bottom', $bottom ? $bottom.'px' : null);
        data_set($properties, 'position.right', $right ? $right.'px' : null);
        data_set($properties, 'width', $width ? $width.'px' : null);
        data_set($properties, 'height', $height ? $height.'px' : null);
    }
}
