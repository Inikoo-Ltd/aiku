<?php

/*
 * author Louis Perez
 * created on 21-06-2026-01h-43m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Models\Web\Webpage;

trait HasFaqDepartmentData
{
    protected function setFaqDepartmentData(Webpage $webpage, array &$webBlock): void
    {
        data_set($webBlock, 'web_block.layout.data.fieldValue.faqs', $webpage->model->faq);
    }
}
