<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Traits\Catalogue\ProductCategory\WithRedoProductCategoryTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class RedoFamiliesTimeSeries
{
    use WithHydrateCommand;
    use WithRedoProductCategoryTimeSeries {
        WithRedoProductCategoryTimeSeries::asCommand insteadof WithHydrateCommand;
    }

    public string $commandSignature = 'families:redo_time_series {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model       = ProductCategory::class;
        $this->restriction = 'family';
    }
}
