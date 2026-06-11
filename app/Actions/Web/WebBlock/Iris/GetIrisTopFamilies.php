<?php

/*
 * author Louis Perez
 * created on 11-06-2026-09h-42m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Traits\WithFamiliesQuery;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisTopFamilies
{
    use AsObject;
    use WithFamiliesQuery;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $families = $this->getFamilyList($webpage)
            ->orderBy('yearly_sales.total_sales', 'desc')
            ->limit(6)
            ->get();

        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamiliesResource::collection($families)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.product_category_title', $webpage->model->name);

        return [
            'type' => $webBlock['type'],
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }
}
