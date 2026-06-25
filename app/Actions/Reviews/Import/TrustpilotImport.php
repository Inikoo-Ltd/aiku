<?php

namespace App\Actions\Reviews\Import;

use App\Actions\Reviews\StoreReview;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Language;
use App\Models\Reviews\Review;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TrustPilotImport implements ToCollection
{
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

        $languages = Language::all()->keyBy('code');

        $sysUser = User::all()->keyBy('username');

        $first = true;
        foreach ($collection as $row) {
            if ($first) {
                $first = false;
                continue;
            }

            /** @var Customer $customer */
            $customer = $customers->get($row[4]);

            if (!$customer) {
                continue;
            }

            if (Review::where('external_id', $row[0])->first()) {
                continue;
            }
            $replyData = [];
            $replay    = $row[10];

            if ($replay) {
                $user = $sysUser->get(strtolower($row[11]));

                $replyData = [
                    'replied'       => true,
                    'reply_message' => $replyData,
                    'reply_at'      => $row[17],
                    'reply_by'      => $user?->id,
                ];
            }

            $meta = [
                'source'                  => 'trustpilot',
                'review_consumer_user_id' => $row[2],
                'review_created'          => $row[1],
            ];

            $reviewData = [
                'customer_id' => $customer->id,
                'rating'      => $row[7],
                'is_public'   => true,
                'title'       => $row[5],
                'message'     => $row[6],
                'language_id' => $languages->get($row[12])->id,
                'external_id' => $row[0],
                'meta'        => $meta,
            ];

            $review = StoreReview::make()->action($this->shop, $reviewData);

            $review->update(
                [
                    'is_online'     => true,
                    'published_at'  => $row[1],
                    'review_status' => ReviewStatusEnum::APPROVED->value,
                    'auto_approve'  => true,
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
}
