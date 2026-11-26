<?php

/*
 * Author: Yudhistira Arya Rajasa Wijaya
 * Created: Thu, 26 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\WebBlock\Iris\GetIrisBlogProductCarousels;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * The product cards one blog product carousel shows on the storefront, in the order it shows them,
 * so the blog workshop previews the carousel while it is being edited, before it is published.
 */
class GetBlogProductCarouselPreview extends OrgAction
{
    use WithWebAuthorisation;

    /**
     * @param  array{source?: ?string, codes?: ?string, family?: ?string, limit?: int|string|null}  $carousel
     *
     * @return array{data: array<int, array<string, mixed>>, currency_code: ?string}
     */
    public function handle(Webpage $webpage, array $carousel): array
    {
        $carousels = GetIrisBlogProductCarousels::make();

        return [
            'data' => $carousels->productsInCarousel($webpage, $carousels->carousel(
                Arr::get($carousel, 'source'),
                Arr::get($carousel, 'codes'),
                Arr::get($carousel, 'family'),
                (string) Arr::get($carousel, 'limit'),
            )),
            'currency_code' => $webpage->shop->currency?->code,
        ];
    }

    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'nullable', 'string', 'in:products,family'],
            'codes'  => ['sometimes', 'nullable', 'string', 'max:4000'],
            'family' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit'  => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, currency_code: ?string}
     */
    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }
}
