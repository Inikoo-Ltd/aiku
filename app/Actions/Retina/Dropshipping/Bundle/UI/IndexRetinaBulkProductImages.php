<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Bundle\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\ImagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Media;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaBulkProductImages extends RetinaAction
{
    public function handle(array $modelData, $prefix = null): Collection
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Media::class);
        $queryBuilder->leftJoin('model_has_media', 'media.id', 'model_has_media.media_id');
        $queryBuilder->whereIn('model_has_media.model_id', Arr::get($modelData, 'product_ids'));
        $queryBuilder->where('model_has_media.model_type', 'Product');


        $queryBuilder
            ->defaultSort('media.id')
            ->select([
                'media.*',
                'model_has_media.sub_scope as sub_scope',
            ]);

        return $queryBuilder->allowedSorts(['size', 'name'])->get();
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id']
        ];
    }

    public function asController(ActionRequest $request): Collection
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }

    public function jsonResponse(Collection $images): AnonymousResourceCollection
    {
        return ImagesResource::collection($images);
    }
}
