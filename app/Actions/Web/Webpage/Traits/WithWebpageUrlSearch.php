<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage\Traits;

use Illuminate\Database\Eloquent\Builder;

trait WithWebpageUrlSearch
{
    /**
     * Finds a webpage from a pasted address, full ("https://www.awgifts.eu/homeware/lamps/lamp-01")
     * or a path ("/homeware/lamps/lamp-01", "lamps/lamp-01"). The url column only keeps the last
     * segment, so the address is matched against the end of canonical_url, whatever the domain.
     */
    protected function orWhereCanonicalUrlEndsWithSearchedPath(Builder $query, string $searchValue): void
    {
        $searchedPath = $this->searchedWebpagePath($searchValue);

        if ($searchedPath === '') {
            return;
        }

        $escapedPath = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchedPath);

        $query->orWhereRaw('webpages.canonical_url COLLATE "C" ILIKE ?', ['%/'.$escapedPath]);
    }

    protected function searchedWebpagePath(string $searchValue): string
    {
        $searchValue = trim($searchValue);

        if (!str_contains($searchValue, '/')) {
            return '';
        }

        $searchValue = preg_replace('/[?#].*$/', '', $searchValue);
        $searchValue = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $searchValue);

        $segments = array_values(array_filter(explode('/', $searchValue), fn (string $segment) => $segment !== ''));

        $startsWithDomain = $segments && str_contains($segments[0], '.');
        if ($startsWithDomain) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }
}
