<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Poll\Hydrate;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\PollOption;

class PollOptionHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(PollOption $pollOption): string
    {
        return $pollOption->id;
    }

    public function handle(PollOption $pollOption): void
    {

        $pollReplies = $pollOption->pollReplies;

        $stats = [
            'number_customers' => $pollReplies->unique('customer_id')->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'state',
                enum: CustomerStateEnum::class,
                models: Customer::class,
                where: function ($q) use ($pollOption) {
                    $q->whereHas('pollReplies', function ($query) use ($pollOption) {
                        $query->where('poll_option_id', $pollOption->id);
                    });
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'status',
                enum: CustomerStatusEnum::class,
                models: Customer::class,
                where: function ($q) use ($pollOption) {
                    $q->whereHas('pollReplies', function ($query) use ($pollOption) {
                        $query->where('poll_option_id', $pollOption->id);
                    });
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'trade_state',
                enum: CustomerTradeStateEnum::class,
                models: Customer::class,
                where: function ($q) use ($pollOption) {
                    $q->whereHas('pollReplies', function ($query) use ($pollOption) {
                        $query->where('poll_option_id', $pollOption->id);
                    });
                }
            )
        );

        if ($pollOption->poll->type == PollTypeEnum::OPTION_REFERRAL_SOURCES) {
            $customerIds = $pollReplies->pluck('customer_id')->unique();

            $stats['number_customer_purchases'] = DB::table('orders')
                ->whereIn('customer_id', $customerIds)
                ->where('state', '!=', 'creating')
                ->select(DB::raw('COUNT(DISTINCT id) as count'))
                ->first()->count ?? 0;

            $stats['total_customer_revenue'] = DB::table('orders')
                ->whereIn('customer_id', $customerIds)
                ->where('state', '!=', 'creating')
                ->select(DB::raw('SUM(total_amount) as total'))
                ->first()->total ?? 0;
        }


        $pollOption->stats()->update($stats);
    }
}
