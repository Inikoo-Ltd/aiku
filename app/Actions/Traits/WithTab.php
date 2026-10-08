<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 00:57:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Arr;

trait WithTab
{
    protected ?string $tab                = null;

    public function withTab(array $tabs, ?string $default = null): static
    {
        $tab =  $this->get('tab') ?? $this->tabOfTableInQuery($tabs) ?? $default ?? Arr::first($tabs);
        if (!in_array($tab, $tabs)) {
            abort(404);
        }
        $this->tab = $tab;

        return $this;
    }

    /**
     * A table search, sort or page change reloads the page without "tab", only with the table's
     * prefixed parameters (products_filter, products_sort, productsPage). Without this the default
     * tab is shown instead, and the default can change while the user is on the page.
     */
    private function tabOfTableInQuery(array $tabs): ?string
    {
        $queryKeys = array_keys(request()->query());

        return collect($tabs)
            ->sortByDesc(fn (string $tab) => strlen($tab))
            ->first(fn (string $tab) => collect($queryKeys)->contains(
                fn ($key) => str_starts_with((string) $key, $tab.'_') || $key === $tab.'Page'
            ));
    }
}
