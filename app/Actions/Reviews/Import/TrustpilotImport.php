<?php

namespace App\Actions\Reviews\Import;

use App\Actions\Reviews\StoreReview;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Language;
use App\Models\Reviews\Review;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TrustpilotImport implements ToCollection
{
    private Shop $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

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
            
            $customer = $customers->get($row[4]);

            if (!$customer) {
                continue;
            }

            if (Review::where('external_id', $row[0])->first()) {
                continue;
            }

            $replyData = $sysUser->get($row[11]) ? [
                'reply_message'     => $row[10],
                'reply_at'          => $row[17],
                'reply_by'          => $sysUser->get($row[11])->id,
            ] : [];

            $meta = [
                'source'                    => 'trustpilot',
                'review_consumer_user_id'   => $row[2],
                'review_created'            => $row[1],
            ];

            $reviewData = [
                'customer_id'       => $customer->id,
                'rating'            => $row[7],
                'is_public'         => (($row[7] ?? 0) > 3),
                'title'             => $row[5],
                'message'           => $row[6],
                'language_id'       => $languages->get($row[12])->id,
                ...$replyData,
                'external_id'       => $row[0],
                'meta'              => $meta,
                'reviewable_type'   => 'shop',
                'reviewable_id'     => $this->shop->id
            ];

            StoreReview::make()->action($this->shop, $reviewData, false);
        }
    }
}
