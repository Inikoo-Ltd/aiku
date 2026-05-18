<?php

namespace App\Actions\Web\Website;

use App\Actions\HydrateModel;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRedirects;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRedirects;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateRedirects;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateRedirects;
use App\Models\Web\Webpage;
use App\Models\Web\Website;

class HydrateRedirect extends HydrateModel
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:redirects {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Webpage::class;
    }

    public function handle(Webpage|Website $parent): void
    {
        if ($parent instanceof Webpage) {
            WebpageHydrateRedirects::run($parent);
            WebsiteHydrateRedirects::run($parent->website);
        } else {
            WebsiteHydrateRedirects::run($parent);
        }
        OrganisationHydrateRedirects::run($parent->organisation);
        GroupHydrateRedirects::run($parent->group);
    }
}
