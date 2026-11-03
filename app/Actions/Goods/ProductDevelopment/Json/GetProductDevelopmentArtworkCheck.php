<?php

/*
 * Author Louis Perez
 * Created on 03-11-2026-14h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Json;

use App\Actions\Goods\ProductDevelopment\Design\CheckProductDevelopmentArtworkWithAi;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentArtwork;
use Lorisleiva\Actions\ActionRequest;

/**
 * The AI pre-check of an artwork's current version, asked for again while it is still running.
 */
class GetProductDevelopmentArtworkCheck extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    /**
     * @return array{check: array<string, mixed>|null}
     */
    public function handle(ProductDevelopment $productDevelopment, ProductDevelopmentArtwork $artwork): array
    {
        abort_unless($artwork->product_development_id === $productDevelopment->id, 404);

        return ['check' => CheckProductDevelopmentArtworkWithAi::payload($artwork->checks()->where('version', $artwork->version)->with('requestedBy')->first())];
    }

    /**
     * @return array{check: array<string, mixed>|null}
     */
    public function asController(ProductDevelopment $productDevelopment, ProductDevelopmentArtwork $artwork, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($productDevelopment, $artwork);
    }
}
