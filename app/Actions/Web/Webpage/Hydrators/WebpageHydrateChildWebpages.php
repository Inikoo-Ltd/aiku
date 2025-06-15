<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Feb 2024 23:37:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateChildWebpages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Webpage $webpage): string
    {
        return $webpage->id;
    }

    public function handle(Webpage $webpage): void
    {
        $stats = [
            'number_child_webpages' => $webpage->webpages()->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'child_webpages',
            field: 'state',
            enum: WebpageStateEnum::class,
            models: Webpage::class,
            where: function ($q) use ($webpage) {
                $q->where('parent_id', $webpage->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'child_webpages',
            field: 'type',
            enum: WebpageTypeEnum::class,
            models: Webpage::class,
            where: function ($q) use ($webpage) {
                $q->where('parent_id', $webpage->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'child_webpages',
            field: 'sub_type',
            enum: WebpageSubTypeEnum::class,
            models: Webpage::class,
            where: function ($q) use ($webpage) {
                $q->where('parent_id', $webpage->id);
            }
        ));

        $webpage->stats()->update($stats);
    }


}
