<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
 */

namespace App\Actions\Traits\WebBlocks;

use App\Actions\Transfers\Aurora\FetchAuroraWebBlockLink;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithFetchImagesWebBlock
{
    use AsAction;

    public function processImagesData(Webpage $webpage, $auroraBlock): ?array
    {
        if (! isset($auroraBlock['images'])) {
            return null;
        }
        $layoutType = $this->getImagesLayoutTypeByResolution($auroraBlock);
        data_set($layout, 'data.fieldValue.layout_type', $layoutType);

        $imagesArray = [];

        $externalLinks = [];
        foreach ($auroraBlock['images'] as $image) {
            $imageLink = null;
            if (! empty($image['link'])) {
                $imageLink = FetchAuroraWebBlockLink::run($this->organisationSource, $webpage->website, $image['link']);
            }
            if (! isset($image['src'])) {
                continue;
            }
            if ($imageLink) {
                if ($imageLink['type'] == 'external') {
                    $externalLinks[] = $imageLink['url'];
                }
            }
            $imagesArray[] = [
                'link_data' => $imageLink,
                'aurora_source' => $image['src'],
            ];
        }

        data_set($layout, 'data.fieldValue.value', $imagesArray);
        data_set($layout, 'external_links', $externalLinks);

        return $layout;
    }

    public function getImagesLayoutTypeByResolution($auroraBlock): ?string
    {
        $images = $auroraBlock['images'];

        $widths = array_column($images, 'width');
        $totalWidth = array_sum($widths);

        // Calculate the ratios of each image width to the total width
        $ratios = array_map(
            function ($width) use ($totalWidth) {
                return round($width / $totalWidth, 2);
            },
            $widths
        );

        $code = '';
        switch (count($ratios)) {
            case 1:
                $code = '1';
                break;
            case 2:
                if ($ratios[0] == 0.50 && $ratios[1] == 0.50) {
                    $code = '2';
                } elseif ($ratios[0] == 0.33 && $ratios[1] == 0.67) {
                    $code = '12';
                } elseif ($ratios[0] == 0.67 && $ratios[1] == 0.33) {
                    $code = '21';
                } elseif ($ratios[0] == 0.25 && $ratios[1] == 0.75) {
                    $code = '13';
                } elseif ($ratios[0] == 0.75 && $ratios[1] == 0.25) {
                    $code = '31';
                }
                break;
            case 3:
                if ($ratios[0] == 0.33 && $ratios[1] == 0.33 && $ratios[2] == 0.33) {
                    $code = '3';
                } elseif ($ratios[0] == 0.50 && $ratios[1] == 0.25 && $ratios[2] == 0.25) {
                    $code = '211';
                }
                break;
            case 4:
                if ($ratios[0] == 0.25 && $ratios[1] == 0.25 && $ratios[2] == 0.25 && $ratios[3] == 0.25) {
                    $code = '4';
                }
                break;
        }

        return $code;
    }
}
