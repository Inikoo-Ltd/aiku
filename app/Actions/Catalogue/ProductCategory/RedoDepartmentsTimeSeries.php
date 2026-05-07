<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Traits\Catalogue\ProductCategory\WithRedoProductCategoryTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class RedoDepartmentsTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand, WithRedoProductCategoryTimeSeries {
        WithRedoProductCategoryTimeSeries::asCommand insteadof WithHydrateCommand;
        WithRedoProductCategoryTimeSeries::asJob insteadof WithHydrateCommand;
    }

    protected ?ProductCategoryTypeEnum $categoryType;

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'departments:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model        = ProductCategory::class;
        $this->categoryType = ProductCategoryTypeEnum::DEPARTMENT;
    }
}
