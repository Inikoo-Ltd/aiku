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
use App\Models\CRM\Customer;
use App\Models\CRM\Poll;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PollHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Poll $poll): string
    {
        return $poll->id;
    }
    public function handle(Poll $poll): void
    {
        $stats = [
            'number_customers' => DB::table('poll_replies')
                ->where('poll_id', $poll->id)
                ->select(DB::raw('COUNT(DISTINCT poll_replies.customer_id) as count'))
                ->first()->count ?? 0,
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'state',
                enum: CustomerStateEnum::class,
                models: Customer::class,
                where: function ($q) use ($poll) {
                    $q->whereHas('pollReplies', function ($query) use ($poll) {
                        $query->where('poll_id', $poll->id);
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
                where: function ($q) use ($poll) {
                    $q->whereHas('pollReplies', function ($query) use ($poll) {
                        $query->where('poll_id', $poll->id);
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
                where: function ($q) use ($poll) {
                    $q->whereHas('pollReplies', function ($query) use ($poll) {
                        $query->where('poll_id', $poll->id);
                    });
                }
            )
        );

        $poll->stats()->update($stats);
    }
}
