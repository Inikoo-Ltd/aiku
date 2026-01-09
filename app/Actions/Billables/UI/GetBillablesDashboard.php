<?php

/*
 * Author: Vika Aqordi
 * Created on 08-01-2026-16h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Billables\UI;

use App\Actions\Traits\HasBucketImages;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketAttachment;

class GetBillablesDashboard
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;

    public function handle(): array
    {


        return [];
    }


}
