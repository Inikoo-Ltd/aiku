<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFaqDepartment
{
    use AsObject;
    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set($webBlock, 'web_block.layout.data.fieldValue.faqs', $webpage->model->faq);
        return $webBlock;
    }
}
