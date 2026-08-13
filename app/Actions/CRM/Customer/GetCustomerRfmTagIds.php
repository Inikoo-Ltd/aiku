<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use App\Models\Helpers\Tag;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerRfmTagIds
{
    use AsObject;

    public const CACHE_KEY = 'customer_rfm_tag_ids';

    public function handle(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            return Tag::whereIn('data->type', CustomerRfmSegmentEnum::types())
                ->pluck('id', 'slug')
                ->toArray();
        });
    }
}
