<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Bundle\UI;

use App\Actions\Catalogue\Product\UI\IndexBulkProductImages;
use App\Actions\RetinaAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaBulkProductImages extends RetinaAction
{
    public function handle(array $modelData, $prefix = null): Collection
    {
        return IndexBulkProductImages::run($modelData, $prefix);
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
        return IndexBulkProductImages::make()->jsonResponse($images);
    }
}
