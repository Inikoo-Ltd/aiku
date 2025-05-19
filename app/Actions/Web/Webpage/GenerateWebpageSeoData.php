<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 10:31:14 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebpages;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebpages;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateChildWebpages;
use App\Actions\Web\Webpage\Search\WebpageRecordSearch;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Models\Web\Webpage;

class GenerateWebpageSeoData extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWebAuthorisation;

    private Webpage $webpage;

    public string $commandSignature = 'webpage:seo-data';


    public function handle(Webpage $webpage, array $modelData): Webpage
    {

        $modelData = StoreWebpageHasRedirect::make()->action($webpage, $modelData);

        $webpage = $this->update($webpage, $modelData, ['data', 'settings']);


        if ($webpage->wasChanged('state')) {
            GroupHydrateWebpages::dispatch($webpage->group)->delay($this->hydratorsDelay);
            OrganisationHydrateWebpages::dispatch($webpage->organisation)->delay($this->hydratorsDelay);
            WebsiteHydrateWebpages::dispatch($webpage->website)->delay($this->hydratorsDelay);
            if ($webpage->parent_id) {
                WebpageHydrateChildWebpages::dispatch($webpage->parent)->delay($this->hydratorsDelay);
            }
        }


        WebpageRecordSearch::run($webpage);

        return $webpage;
    }

    public function asCommand()
    {
        $webpages = Webpage::all();

        foreach ($webpages as $webpage) {
            $this->handle($webpage, [
                'seo_data' => [
                    'meta_title' => $webpage->title,
                    'meta_description' => $webpage->description,
                    'structured_data' => (object) [],
                    'meta_keywords' => (object) []
                ]
            ]);
        }
    }
}
