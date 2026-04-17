<?php

namespace App\Actions\Catalogue\Shop\Json;

use App\Actions\IrisAction;
use App\Http\Resources\Reviews\ReviewIoResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class FetchProductReviewThirdParty extends IrisAction
{
    private $provider = null;
    public function handle(Product $product, array $modelData): array|string
    {
        $this->provider = data_get($this->shop->settings, 'reviews.provider');

        if (!$this->provider) {
            abort(422, 'Unable to fetch Review from Third Party. Review setting on shop is not set up');
        }

        $reviews = [];

        if ($this->provider == 'reviews.io') {
            $reviews = $this->shop->retrieveReviewsAndQuestions($product, data_get($modelData, 'type', null));
        }

        return $reviews;

    }

    public function jsonResponse(array $reviewData)
    {

        if ($this->provider == 'reviews.io') {
            return [
                'reviews'    => ReviewIoResource::collection(data_get($reviewData, 'reviews', null)),
                'store_data'   => [
                    'store_name'        => data_get($reviewData, 'store_name', null),
                    'review_count'      => data_get($reviewData, 'stats.company.review_count', null),
                    'average_rating'    => data_get($reviewData, 'stats.company.average_rating', null),
                ],
                'verdict'           => data_get($reviewData, 'verdict', null),
                'review_count'      => data_get($reviewData, 'review_count', null),
                'results_count'     => data_get($reviewData, 'results_count', null),
                'average_rating'    => data_get($reviewData, 'average_rating', null),
            ];
        }

        return $reviewData;
    }

    public function rules(): array
    {
        return [
            'type'    => ['sometimes', 'string']
        ];
    }

    public function asController(Product $product, ActionRequest $request): array|string
    {
        $this->initialisation($request);

        return $this->handle($product, $this->validatedData);
    }
}
