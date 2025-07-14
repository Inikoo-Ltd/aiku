<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Image;

use App\Actions\Api\Retina\Dropshipping\Resource\ImageResource;
use App\Actions\RetinaApiAction;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Media;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\Validator;

class GetImages extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    private Product|Portfolio $model;

    public function handle(): LengthAwarePaginator
    {
        $model = $this->model;
        $query = QueryBuilder::for(Media::class);
        if ($model instanceof Portfolio) {
            $query->join('model_has_media', 'media.id', '=', 'model_has_media.media_id')
                ->where('model_has_media.model_id', $model->item_id)
                ->where('model_has_media.model_type', $model->item_type);
        } else {
            $query->join('model_has_media', 'media.id', '=', 'model_has_media.media_id')
                ->where('model_has_media.model_id', $model->id)
                ->where('model_has_media.model_type', class_basename(Product::class));
        }

        return $query->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle();
    }

    public function jsonResponse(LengthAwarePaginator $images): AnonymousResourceCollection
    {
        return ImageResource::collection($images);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:portfolio,product'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'id' => $request->query('id'),
                'type' => $request->query('type'),
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
            ]
        );
    }

    public function afterValidator(Validator $validator): void
    {
        $id = $this->get('id');
        $type = $this->get('type');

        if ($type === 'portfolio') {
            $this->model = Portfolio::find($id);
            if (!$this->model) {
                $validator->errors()->add('id', 'Portfolio not found');
            }
        } elseif ($type === 'product') {
            $this->model = Product::find($id);
            if (!$this->model) {
                $validator->errors()->add('id', 'Product not found');
            }
        }
    }
}
