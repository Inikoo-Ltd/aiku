<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
 */

namespace App\Actions\Traits\WebBlocks;

use Lorisleiva\Actions\Concerns\AsAction;

trait WithFetchProductWebBlock
{
    use AsAction;

    public function processProductData($auroraBlock): array
    {

        data_set($layout, 'data.fieldValue.value.text', $auroraBlock['text']);

        $otherImages = [];
        foreach ($auroraBlock['other_images'] as $image) {
            if (! isset($image['src'])) {
                continue;
            }
            $otherImages[] = [
                'aurora_source' => $image['src'],
            ];
        }
        // format of array should be [["aurora_source"=> ""], ["aurora_source"=> ""]]
        $imagesArray = $otherImages + [['aurora_source' => $auroraBlock['image']['src']]];
        data_set($layout, 'data.fieldValue.value.images', $imagesArray);

        return $layout;
    }
}
