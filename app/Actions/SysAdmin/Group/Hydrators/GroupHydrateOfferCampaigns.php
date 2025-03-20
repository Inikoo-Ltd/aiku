<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 22:02:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOfferCampaigns
{
    use AsAction;
    use WithEnumStats;

    private Group $group;
    public string $jobQueue = 'low-priority';
    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_offer_campaigns'          => $group->offerCampaigns()->count(),
            'number_current_offer_campaigns'  => $group->offerCampaigns()->where('status', true)->count(),

        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'offer_campaigns',
                field: 'state',
                enum: OfferCampaignStateEnum::class,
                models: OfferCampaign::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );


        $group->discountsStats()->update($stats);
    }


}
