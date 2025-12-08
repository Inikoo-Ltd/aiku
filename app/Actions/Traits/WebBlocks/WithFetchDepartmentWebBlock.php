<?php

/*
 * author Arya Permana - Kirin
 * created on 09-10-2024-11h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Traits\WebBlocks;

use App\Transfers\Aurora\WithAuroraParsers;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithFetchDepartmentWebBlock
{
    use AsAction;
    use WithAuroraParsers;

    public function processDepartmentData(
        $webpage,
        $auroraBlock
    ): ?array {
        if (! isset($auroraBlock['type'])) {
            return null;
        }
        $sections = [];
        data_set($layout, 'data.fieldValue.value.department_id', $webpage->model_id);
        foreach ($auroraBlock['sections'] as $section) {
            $sections[] = [
                'title' => $section['title'] ?? '',
                'subtitle' => $section['subtitle'] ?? '',
                'items' => array_filter(
                    array_map(function ($item) {
                        if ($item['type'] == 'image') {
                            return [
                                'type' => $item['type'],
                                'aurora_source' => $item['image_src'],
                            ];
                        }

                        return $item;
                    }, $section['items'] ?? [])
                ),
            ];
        }

        data_set($layout, 'data.fieldValue.value.sections', $sections);

        return $layout;
    }
}
