<?php

/*
 * Author Louis Perez
 * Created on 25-06-2026-15h-24m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Reviews\Import;

use App\Actions\Reviews\StoreReview;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\ToCollection;

class ReviewIOImport implements ToCollection
{
    use RemembersRowNumber;
    private Shop $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    /**
     * @throws \Throwable
     */
    public function collection(Collection $collection): void
    {
        $customers = Customer::where('shop_id', $this->shop->id)
            ->get()
            ->keyBy('email');

        $language_id = $this->shop->language_id;

        $first = true;
        $isShopCSV = false;

        $orderColumnPos = null;
        $customerEmailColumnPos = null;

        foreach ($collection as $row) {
            if ($first) {
                $first = false;

                $arrayRow = $row->toArray();
                $isShopCSV = in_array('nps', $arrayRow);
                // To get column number, handling different ReviewIO format that idk how and why they gave us that way
                $columnNumber = array_flip($arrayRow);

                $orderColumnPos                 = data_get($columnNumber, 'order_id');
                $customerEmailColumnPos         = data_get($columnNumber, 'email');
                $reviewCommentColumnPos         = data_get($columnNumber, 'comments');
                $widgetFingerprintColumnPos     = data_get($columnNumber, 'widget_fingerprint');
                $ratingColumnPos                = data_get($columnNumber, 'rating');
                $replyColumnPos                 = data_get($columnNumber, 'reply');
                $createdDateColumnPos           = data_get($columnNumber, 'date_created');
                $productSkuColumnPos            = data_get($columnNumber, 'product_sku');
                $publishedImageColumnPos        = data_get($columnNumber, 'published_images');
                $publishedVideoColumnPos        = data_get($columnNumber, 'published_videos');

                continue;
            }

            /** @var Customer $customer */
            $customer = $customers->get($row[$customerEmailColumnPos]);

            if (!$customer) {
                continue;
            }

            $scope = $this->shop;

            $orderColumn = $row[$orderColumnPos];
            $order = Order::where('customer_id', $customer->id)->where('reference', $orderColumn)->first();
            if ($order) {
                $scope = $order;
            }

            $reviewComment = $row[$reviewCommentColumnPos];
            // Generate manually if doesn't exists. Idk, Raul would need to take a look at this later.
            // I used widget fingerprint currently as it is the most unique column that exists on both Product & Shop CSV, but it is nullable, and there's no other unique column
            // So created custom checksum for this.
            $externalId = $row[$widgetFingerprintColumnPos] ?? hash('sha256', "reviewIO.{$orderColumn}.{$reviewComment}");
            $review = Review::where('external_id', $externalId)->first();

            if ($review) {
                continue;
            }

            $reviewData = array_filter([
                'order_id'          => $order?->id,
                'customer_id'       => $customer->id,
                'language_id'       => $language_id,
                'rating'            => $row[$ratingColumnPos],
                'message'           => is_scalar($reviewComment) ? (string) $reviewComment : '',
                'external_id'       => $externalId,
            ]);

            $replyData = [];
            $meta = [];
            $webImages = null;

            if ($isShopCSV) {
                $replyData = [
                    'replied'           => (bool) $row[$replyColumnPos],
                    'reply_message'     => $row[$replyColumnPos]
                ];

                $meta = [
                    'source'                  => 'ReviewIO',
                    'review_created'          => $row[$createdDateColumnPos],
                ];

                $webImages = [
                    'main'      => explode(';', $row[$publishedImageColumnPos]),
                    'videos'    => explode(';', $row[$publishedVideoColumnPos]),
                ];
            } else {
                $product = Product::where('shop_id', $this->shop->id)->where('code', $row[$productSkuColumnPos])->first();

                if ($product) {
                    $scope = $product;
                }

                $replyData = [
                    'replied'           => (bool) $row[$replyColumnPos],
                    'reply_message'     => $row[$replyColumnPos]
                ];

                $meta = [
                    'source'                  => 'ReviewIO',
                    'review_created'          => $row[$createdDateColumnPos],
                ];

                $webImages = [
                    'main'      => explode(';', $row[$publishedImageColumnPos]),
                    'videos'    => explode(';', $row[$publishedVideoColumnPos]),
                ];
            }

            data_set($reviewData, 'meta', $meta);
            // Handle null webImages
            $webImages = array_filter($webImages);
            if (!empty($webImages)) {
                data_set($reviewData, 'web_images', $webImages);
            }

            $review = StoreReview::make()->action($scope, $reviewData);

            $review->update(
                [
                    'is_online'     => true,
                    'published_at'  => Carbon::parse(
                        $row[$createdDateColumnPos]
                    ),
                    'review_status' => ReviewStatusEnum::APPROVED->value,
                    'auto_approved' => true,
                    'approved'      => true,
                    'state'         => ReviewStateEnum::PUBLISHED->value
                ]
            );

            if (!empty($replyData)) {
                $review->update(
                    $replyData
                );
            }
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    // DO NOT DELETE, STILL NEED TO CHECK LATER

    // # SHOP CSV
    // "nps", // 0
    // "order_id", // 1
    // "comments", // 2
    // "rating", // 3
    // "status", // 4
    // "investigate_reason", // 5
    // "date_created", // 6
    // "author", // 7
    // "email", // 8
    // "video_review_prompt_id", // 9
    // "widget_fingerprint", // 10
    // "reviewer_desc", // 11
    // "video_first_campaign", // 12
    // "tags", // 13
    // "location", // 14
    // "reply", // 15
    // "reply_private", // 16
    // "reply_date", // 17
    // "published_images", // 18
    // "unpublished_images", // 19
    // "published_videos", // 20
    // "unpublished_videos", // 21
    // "source", // 22
    // "address", // 23
    // "branch", // 24

    // # PRODUCT CSV
    // "order_id", // 0
    // "review_title", // 1
    // "comments", // 2
    // "rating", // 3
    // "status", // 4
    // "date_created", // 5
    // "sku", // 6
    // "imported", // 7
    // "imported_from", // 8
    // "author", // 9
    // "email", // 10
    // "product_sku", // 11
    // "product_name", // 12
    // "product_link", // 13
    // "product_category", // 14
    // "product_lookup", // 15
    // "platform_product_id", // 16
    // "video_review_prompt_id", // 17
    // "iovation_blackbox", // 18
    // "handle", // 19
    // "app_id", // 20
    // "guid", // 21
    // "verified_by_shop", // 22
    // "widget_fingerprint", // 23
    // "detected_language", // 24
    // "incentivized_description", // 25
    // "video_first_campaign", // 26
    // "tags", // 27
    // "location", // 28
    // "reply", // 29
    // "reply_private", // 30
    // "reply_date", // 31
    // "published_images", // 32
    // "unpublished_images", // 33
    // "published_videos", // 34
    // "unpublished_videos", // 35
    // "source", // 36
    // "address", // 37
    // "timeago", // 38
}
