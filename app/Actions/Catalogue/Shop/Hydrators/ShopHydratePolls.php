<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePolls implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }
    public function handle(Shop $shop): void
    {

        $stats         = [
            'number_polls'                   => $shop->polls->count(),
            'number_polls_in_registration' => $shop->polls->where('in_registration', true)->count(),
            'number_polls_in_registration_type_open_question' => $shop->polls->where('in_registration', true)->where('type', PollTypeEnum::OPEN_QUESTION)->count(),
            'number_polls_in_registration_type_option' => $shop->polls->where('in_registration', true)->where('type', PollTypeEnum::OPTION)->count(),
            'number_polls_in_iris'       => $shop->polls->where('in_iris', true)->count(),
            'number_polls_in_iris_type_open_question' => $shop->polls->where('in_iris', true)->where('type', PollTypeEnum::OPEN_QUESTION)->count(),
            'number_polls_in_iris_type_option' => $shop->polls->where('in_iris', true)->where('type', PollTypeEnum::OPTION)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'polls',
                field: 'type',
                enum: PollTypeEnum::class,
                models: Poll::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );
        $shop->crmStats()->update($stats);
    }


}
