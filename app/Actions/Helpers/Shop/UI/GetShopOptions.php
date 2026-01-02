<?php

namespace App\Actions\Helpers\Shop\UI;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopOptions
{
    use AsObject;

    public function handle(?string $organisationSlug = null): array
    {

        if (! $organisationSlug) {
            return [];
        }

        $query = Shop::query()
            ->where('state', 'open')
            ->whereHas('organisation', function ($q) use ($organisationSlug) {
                $q->where('slug', $organisationSlug);
            });

        $selectOptions = [];

        foreach ($query->get() as $shop) {
            $selectOptions[$shop->id] = [
                'label' => $shop->code . ' | ' . $shop->name,
            ];
        }

        return $selectOptions;
    }
}
