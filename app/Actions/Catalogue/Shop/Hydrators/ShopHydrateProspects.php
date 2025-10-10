<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:34 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Events\BroadcastProspectsDashboard;
use App\Models\CRM\Prospect;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateProspects implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_prospects'                 => $shop->prospects()->where('shop_id', $shop->id)->count(),
            'number_opt_in_prospects'                 => $shop->prospects()->where('shop_id', $shop->id)->where('is_opt_in', true)->count(),
            'number_opt_out_prospects'                 => $shop->prospects()->where('shop_id', $shop->id)->where('is_opt_in', false)->count(),
            'number_prospects_dont_contact_me' => $shop->prospects()->where('shop_id', $shop->id)->where('dont_contact_me', true)->count(),
            'number_opt_in_prospects_dont_contact_me' => $shop->prospects()->where('shop_id', $shop->id)->where('dont_contact_me', true)->where('is_opt_in', true)->count(),
            'number_opt_out_prospects_dont_contact_me' => $shop->prospects()->where('shop_id', $shop->id)->where('dont_contact_me', true)->where('is_opt_in', false)->count(),

        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'prospects',
                field: 'state',
                enum: ProspectStateEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_in_prospects',
                field: 'state',
                enum: ProspectStateEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', true);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_out_prospects',
                field: 'state',
                enum: ProspectStateEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', false);
                }
            )
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'prospects',
                field: 'contacted_state',
                enum: ProspectContactedStateEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_in_prospects',
                field: 'contacted_state',
                enum: ProspectContactedStateEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', true);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_out_prospects',
                field: 'contacted_state',
                enum: ProspectContactedStateEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', false);
                }
            )
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'prospects',
                field: 'fail_status',
                enum: ProspectFailStatusEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_in_prospects',
                field: 'fail_status',
                enum: ProspectFailStatusEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', true);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_out_prospects',
                field: 'fail_status',
                enum: ProspectFailStatusEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', false);
                }
            )
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'prospects',
                field: 'success_status',
                enum: ProspectSuccessStatusEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_in_prospects',
                field: 'success_status',
                enum: ProspectSuccessStatusEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', true);
                }
            )
        );
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'opt_out_prospects',
                field: 'success_status',
                enum: ProspectSuccessStatusEnum::class,
                models: Prospect::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)
                        ->where('is_opt_in', false);
                }
            )
        );

        $crmStats = $shop->crmStats;
        $crmStats->update($stats);
        BroadcastProspectsDashboard::dispatch($crmStats->getChanges());

    }
}
