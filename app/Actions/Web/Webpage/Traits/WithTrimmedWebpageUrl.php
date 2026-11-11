<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage\Traits;

trait WithTrimmedWebpageUrl
{
    /**
     * The url is joined to its parent path with a slash, so a slash typed at either end would
     * give a double slash in the canonical url (e.g. "product-guides//my-article").
     */
    protected function trimSlashesFromUrl(): void
    {
        $url = $this->get('url');

        if (is_string($url)) {
            $this->set('url', trim($url, " /"));
        }
    }
}
