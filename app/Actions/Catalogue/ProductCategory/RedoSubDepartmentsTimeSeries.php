<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Traits\Catalogue\ProductCategory\WithRedoProductCategoryTimeSeries;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RedoSubDepartmentsTimeSeries implements ShouldBeUnique
{
    use AsAction;
    use WithRedoProductCategoryTimeSeries;

    protected ?ProductCategoryTypeEnum $restriction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'sub-departments:redo_time_series {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->restriction = ProductCategoryTypeEnum::SUB_DEPARTMENT;
    }
}
