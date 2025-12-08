<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Models\Portfolio\PortfolioWebsite;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPortfolioWebsiteFromDomain extends OrgAction
{
    use AsAction;

    public function handle(string $domain): PortfolioWebsite
    {
        $origin = $domain ? preg_replace('/^(https?:\/\/)?(www\.)?([^\/]+).*/', '$3', $domain) : null;

        return PortfolioWebsite::where('url', 'LIKE', '%'.$origin.'%')->firstOrFail();
    }
}
