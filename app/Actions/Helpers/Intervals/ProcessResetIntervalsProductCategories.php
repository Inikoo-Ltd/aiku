<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsProductCategories
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'aiku:process-reset-intervals-product-categories';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {



    }
}
