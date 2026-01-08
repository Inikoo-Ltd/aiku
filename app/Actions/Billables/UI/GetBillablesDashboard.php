<?php

/*
 * Author: Vika Aqordi
 * Created on 08-01-2026-16h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Billables\UI\GetBillablesDashboard;

use App\Actions\Traits\HasBucketImages;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Inventory\OrgStock\Json\GetOrgStocksInProduct;
use App\Actions\Traits\HasBucketAttachment;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Inventory\OrgStocksResource;

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
