<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 16:06:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Hydrators;

use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateUniversalSearch
{
    use AsAction;
    public string $jobQueue = 'universal-search';

    public function handle(Website $website): void
    {
        $website->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $website->group_id,
                'organisation_id'   => $website->organisation_id,
                'organisation_slug' => $website->organisation->slug,
                'shop_id'           => $website->shop_id,
                'shop_slug'         => $website->shop->slug,
                'website_id'        => $website->id,
                'website_slug'      => $website->slug,
                'section'           => 'web',
                'title'             => trim($website->code.' '.$website->name.' '.$website->domain),
                'description'       => ''
            ]
        );
    }


}
