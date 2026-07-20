<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchReviews
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $reviewsQuery = Review::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $reviewsQuery->where('shop_id', $shopId);
        }

        return [
            'scope'   => 'reviews',
            'results' => [
                'reviews' => array_map(static fn (array $document) => [
                    'id'     => (int)$document['id'],
                    'code'   => $document['customer_name'] ?: 'Review #'.$document['id'],
                    'name'   => Str::limit((string)($document['message'] ?? ''), 120),
                    'rating' => $document['rating'] ?? null,
                    'state'  => $document['status'] ?? null,
                ], $this->rawDocuments($reviewsQuery)),
            ],
        ];
    }


}
