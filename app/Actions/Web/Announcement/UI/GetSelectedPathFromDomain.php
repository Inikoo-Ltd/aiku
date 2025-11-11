<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSelectedPathFromDomain extends OrgAction
{
    use AsAction;

    public function handle(string $domain): string|null
    {
        $path = $domain ? preg_replace('/^(https?:\/\/)?(www\.)?[^\/]+\/?(.*)$/', '$3', $domain) : null;

        return $path === '' ? null : $path;
    }
}
