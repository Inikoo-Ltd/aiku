<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Upload\UI;

use App\Http\Resources\Helpers\UploadReportResource;
use App\Models\Helpers\Upload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexUploadReports
{
    use AsAction;

    public function handle(Model $parent, string $model, string $prefix, int $perPage = 10): AnonymousResourceCollection
    {
        return UploadReportResource::collection(
            Upload::query()
                ->with('user')
                ->where('parent_type', $parent->getMorphClass())
                ->where('parent_id', $parent->getKey())
                ->where('model', $model)
                ->orderByDesc('id')
                ->paginate(perPage: $perPage, pageName: $prefix.'Page')
                ->withQueryString()
        );
    }
}
