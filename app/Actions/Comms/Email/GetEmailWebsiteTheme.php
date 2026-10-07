<?php

namespace App\Actions\Comms\Email;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEmailWebsiteTheme
{
    use AsAction;

    /**
     * The published colours and font of the shop's website, offered in the email workshop so emails can match it.
     *
     * @return array{color: array<int, string>, fontFamily: string|null}|null
     */
    public function handle(?Shop $shop): ?array
    {
        $theme  = Arr::get($shop?->website?->published_layout ?? [], 'theme', []);
        $colors = array_values(array_filter(Arr::get($theme, 'color', []), 'is_string'));

        if ($colors === []) {
            return null;
        }

        return [
            'color'      => $colors,
            'fontFamily' => Arr::get($theme, 'container.properties.text.fontFamily') ?? Arr::get($theme, 'fontFamily'),
        ];
    }
}
