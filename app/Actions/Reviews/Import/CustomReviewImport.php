<?php

/*
 * Author: Yudhistira A <aryarajasa0@gmail.com>
 * Created: Thu, 17 Sep 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Reviews\Import;

use App\Actions\Reviews\StoreReview;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\Review;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class CustomReviewImport implements ToCollection
{
    public function __construct(private Shop $shop)
    {
    }

    /**
     * @throws \Throwable
     */
    public function collection(Collection $collection): void
    {
        $columns = null;

        foreach ($collection as $row) {
            $row = collect($row)->toArray();

            if ($columns === null) {
                $columns = array_flip(array_map(
                    fn ($header) => trim(str_replace("\u{FEFF}", '', (string) $header)),
                    $row
                ));

                continue;
            }

            $author        = trim((string) $row[$columns['author']]);
            $message       = trim((string) $row[$columns['review']]);
            $source        = trim((string) $row[$columns['source']]);
            $date          = trim((string) $row[$columns['date']]);
            $response      = trim((string) $row[$columns['response']]);
            $responseDate  = trim((string) $row[$columns['response_date']]);

            if (!$date || !$row[$columns['rating']]) {
                continue;
            }

            $externalId = hash('sha256', "customReview.$source.$author.$date.$message");

            if (Review::where('external_id', $externalId)->exists()) {
                continue;
            }

            $parsedDate = Carbon::parse($date);

            $review = StoreReview::make()->action($this->shop, [
                'rating'      => (int) $row[$columns['rating']],
                'message'     => $message,
                'language_id' => $this->shop->language_id,
                'external_id' => $externalId,
                'meta'        => [
                    'source'         => $source ?: 'Custom',
                    'author_name'    => $author,
                    'review_created' => $parsedDate,
                ],
            ]);

            $review->update([
                'is_online'     => true,
                'created_at'    => $parsedDate,
                'published_at'  => $parsedDate,
                'review_status' => ReviewStatusEnum::APPROVED->value,
                'auto_approved' => true,
                'approved'      => true,
                'state'         => ReviewStateEnum::PUBLISHED->value,
            ]);

            if ($response) {
                $review->update([
                    'replied'       => true,
                    'reply_message' => $response,
                    'reply_at'      => $responseDate ? Carbon::parse($responseDate) : null,
                ]);
            }
        }
    }
}
