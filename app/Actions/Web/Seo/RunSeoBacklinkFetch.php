<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Runs the weekly backlink fetch for one shop's website and competitors at once, link lists
 * included, so the flow can be tried locally. Only routed in the local environment, like
 * `RunSeoRankChecks`.
 */
class RunSeoBacklinkFetch extends OrgAction
{
    use WithSeoEditAuthorisation;

    /**
     * @throws ValidationException
     */
    public function handle(Shop $shop): int
    {
        try {
            return FetchBacklinks::run($shop->website, true);
        } catch (DataForSeoException $e) {
            throw ValidationException::withMessages(['fetch' => $e->getMessage()]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function asController(Shop $shop, ActionRequest $request): int
    {
        abort_unless(app()->environment('local') && $shop->website, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
