<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentsThree;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockSubDepartmentsThree
{
    use AsObject;
    use HasSubDepartmentsThree;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $webBlock = $this->getSubDepartmentsThree($webpage, $webBlock);

        data_set($webBlock, 'web_block.layout.data.fieldValue.department', $webpage->model);

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
