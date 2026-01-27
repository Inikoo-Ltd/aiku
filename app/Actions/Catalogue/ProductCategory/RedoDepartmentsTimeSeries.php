<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Traits\Catalogue\ProductCategory\WithRedoProductCategoryTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class RedoDepartmentsTimeSeries
{
    use WithHydrateCommand;
    use WithRedoProductCategoryTimeSeries {
        WithRedoProductCategoryTimeSeries::asCommand insteadof WithHydrateCommand;
    }

    public string $commandSignature = 'departments:redo_time_series {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';
    public string $jobQueue = 'default-long';

    public function __construct()
    {
        $this->model       = ProductCategory::class;
        $this->restriction = 'department';
    }
}
