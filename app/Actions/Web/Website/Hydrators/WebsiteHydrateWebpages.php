<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 01:12:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateWebpages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    public function handle(Website $website): void
    {
        $stats = [
            'number_webpages' => $website->webpages->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'webpages',
            field: 'state',
            enum: WebpageStateEnum::class,
            models: Webpage::class,
            where: function ($q) use ($website) {
                $q->where('website_id', $website->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'webpages',
            field: 'type',
            enum: WebpageTypeEnum::class,
            models: Webpage::class,
            where: function ($q) use ($website) {
                $q->where('website_id', $website->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'webpages',
            field: 'sub_type',
            enum: WebpageSubTypeEnum::class,
            models: Webpage::class,
            where: function ($q) use ($website) {
                $q->where('website_id', $website->id);
            }
        ));

        $website->webStats()->update($stats);
    }


}
